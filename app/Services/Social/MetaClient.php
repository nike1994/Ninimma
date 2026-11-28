<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Meta Graph API: Strony na Facebooku i konta Instagram firmowe/twórców połączone ze Stroną.
 * Uprawnienia: pages_show_list, pages_read_engagement, pages_manage_posts, read_insights,
 * instagram_basic, instagram_content_publish, instagram_manage_insights, business_management.
 */
class MetaClient
{
    public const VERSION = 'v23.0';

    public const SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'read_insights',
        'instagram_basic', 'instagram_content_publish', 'instagram_manage_insights', 'business_management'];

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.self::VERSION.'/'.ltrim($path, '/');
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://www.facebook.com/'.self::VERSION.'/dialog/oauth?'.http_build_query([
            'client_id' => Integrations::key('meta'), 'redirect_uri' => Integrations::redirectUri('meta'),
            'state' => $state, 'scope' => implode(',', self::SCOPES), 'response_type' => 'code',
        ]);
    }

    /** Kod z logowania → długoterminowy token użytkownika (60 dni; tokeny Stron z niego nie wygasają). */
    public function exchangeCode(string $code): string
    {
        $short = $this->check(Http::timeout(20)->get($this->url('oauth/access_token'), [
            'client_id' => Integrations::key('meta'), 'client_secret' => Integrations::secret('meta'),
            'redirect_uri' => Integrations::redirectUri('meta'), 'code' => $code,
        ]))['access_token'];

        return $this->check(Http::timeout(20)->get($this->url('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token', 'client_id' => Integrations::key('meta'),
            'client_secret' => Integrations::secret('meta'), 'fb_exchange_token' => $short,
        ]))['access_token'];
    }

    /** Strony użytkownika z tokenami i połączonym kontem Instagram. */
    public function pages(string $userToken): array
    {
        $data = $this->check(Http::timeout(20)->get($this->url('me/accounts'), [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 100, 'access_token' => $userToken,
        ]));

        return $data['data'] ?? [];
    }

    /** Adres zdjęcia profilu Strony albo konta Instagram (do pobrania i zapisania u nas). */
    public function profilePicture(SocialAccount $acc): ?string
    {
        if ($acc->platform === \App\Enums\Platform::Instagram) {
            return $this->get($acc->external_id, ['fields' => 'profile_picture_url'], $acc->access_token)['profile_picture_url'] ?? null;
        }

        return $this->get($acc->external_id.'/picture', ['redirect' => 0, 'type' => 'large'], $acc->access_token)['data']['url'] ?? null;
    }

    // ---------------------------------------------------------------- publikacja

    /** Facebook: tekst, zdjęcie, kilka zdjęć albo film. Zwraca [id posta, link]. */
    public function publishFacebook(SocialAccount $acc, string $caption, array $images, ?string $videoUrl): array
    {
        $page = $acc->external_id;
        $token = $acc->access_token;
        if ($videoUrl) {
            $id = $this->post("{$page}/videos", ['file_url' => $videoUrl, 'description' => $caption], $token)['id'];
        } elseif (count($images) === 1) {
            $r = $this->post("{$page}/photos", ['url' => $images[0], 'caption' => $caption, 'published' => 'true'], $token);
            $id = $r['post_id'] ?? $r['id'];
        } elseif ($images) {
            $media = [];
            foreach (array_slice($images, 0, 10) as $i => $url) {
                $media["attached_media[{$i}]"] = json_encode(['media_fbid' => $this->post("{$page}/photos", ['url' => $url, 'published' => 'false'], $token)['id']]);
            }
            $id = $this->post("{$page}/feed", ['message' => $caption] + $media, $token)['id'];
        } else {
            $id = $this->post("{$page}/feed", ['message' => $caption], $token)['id'];
        }
        // Post już istnieje — błąd pobrania linku nie może oznaczyć publikacji jako nieudanej (ponowienie = duplikat).
        try {
            $link = $this->get($id, ['fields' => 'permalink_url'], $token)['permalink_url'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $link = null;
        }

        return [$id, $link];
    }

    /** Instagram, krok 1: kontener (zdjęcie, rolka albo karuzela). */
    public function createInstagramContainer(SocialAccount $acc, string $caption, array $images, ?string $videoUrl): string
    {
        $ig = $acc->external_id;
        $token = $acc->access_token;
        if ($videoUrl) {
            return $this->post("{$ig}/media", ['media_type' => 'REELS', 'video_url' => $videoUrl, 'caption' => $caption, 'share_to_feed' => 'true'], $token)['id'];
        }
        if (! $images) {
            throw new ApiException(__('api.ig_needs_media'), retryable: false);
        }
        if (count($images) === 1) {
            return $this->post("{$ig}/media", ['image_url' => $images[0], 'caption' => $caption], $token)['id'];
        }
        $children = array_map(fn ($url) => $this->post("{$ig}/media", ['image_url' => $url, 'is_carousel_item' => 'true'], $token)['id'], array_slice($images, 0, 10));

        return $this->post("{$ig}/media", ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption], $token)['id'];
    }

    /** Instagram, krok 2: stan przetwarzania kontenera (FINISHED / IN_PROGRESS / ERROR / EXPIRED). */
    public function containerStatus(SocialAccount $acc, string $container): string
    {
        return $this->get($container, ['fields' => 'status_code'], $acc->access_token)['status_code'] ?? 'IN_PROGRESS';
    }

    /** Instagram, krok 3: publikacja kontenera. Zwraca [id, link]. */
    public function publishInstagramContainer(SocialAccount $acc, string $container): array
    {
        $id = $this->post("{$acc->external_id}/media_publish", ['creation_id' => $container], $acc->access_token)['id'];
        $link = $this->get($id, ['fields' => 'permalink'], $acc->access_token)['permalink'] ?? null;

        return [$id, $link];
    }

    // ---------------------------------------------------------------- statystyki

    /** Zasięg, reakcje, komentarze, udostępnienia, kliknięcia. Brakujące metryki = null (Meta zmienia listy metryk). */
    public function facebookStats(SocialAccount $acc, string $postId): array
    {
        $t = $acc->access_token;
        $base = $this->get($postId, ['fields' => 'shares,comments.summary(true).limit(0),reactions.summary(true).limit(0)'], $t);
        $out = ['reactions' => $base['reactions']['summary']['total_count'] ?? null, 'comments' => $base['comments']['summary']['total_count'] ?? null,
            'shares' => $base['shares']['count'] ?? 0, 'reach' => null, 'clicks' => null, 'views' => null];
        foreach (['post_impressions_unique' => 'reach', 'post_clicks' => 'clicks'] as $metric => $key) {
            try {
                $out[$key] = $this->get("{$postId}/insights", ['metric' => $metric], $t)['data'][0]['values'][0]['value'] ?? null;
            } catch (ApiException $e) {
                if ($e->tokenInvalid) {
                    throw $e;
                }
            }
        }

        return $out;
    }

    public function instagramStats(SocialAccount $acc, string $mediaId, bool $video): array
    {
        $metrics = $video ? ['reach', 'likes', 'comments', 'shares', 'saved', 'views'] : ['reach', 'likes', 'comments', 'shares', 'saved'];
        $vals = [];
        foreach ($this->get("{$mediaId}/insights", ['metric' => implode(',', $metrics)], $acc->access_token)['data'] ?? [] as $row) {
            $vals[$row['name']] = $row['values'][0]['value'] ?? ($row['total_value']['value'] ?? null);
        }

        return ['reach' => $vals['reach'] ?? null, 'reactions' => isset($vals['likes']) ? $vals['likes'] + ($vals['saved'] ?? 0) : null,
            'comments' => $vals['comments'] ?? null, 'shares' => $vals['shares'] ?? null, 'clicks' => null, 'views' => $vals['views'] ?? null];
    }

    // ----------------------------------------------------------------

    private function get(string $path, array $query, string $token): array
    {
        return $this->check(Http::timeout(30)->retry(2, 500, throw: false)->get($this->url($path), $query + ['access_token' => $token]));
    }

    private function post(string $path, array $form, string $token): array
    {
        return $this->check(Http::timeout(120)->asForm()->post($this->url($path), $form + ['access_token' => $token]));
    }

    private function check(Response $r): array
    {
        $json = $r->json() ?? [];
        if ($r->successful() && ! isset($json['error'])) {
            return $json;
        }
        $err = $json['error'] ?? [];
        $code = (int) ($err['code'] ?? 0);
        $msg = trim(($err['error_user_msg'] ?? $err['message'] ?? 'HTTP '.$r->status()).' (Meta '.$code.')');
        // 190 = token nieważny; 10/200 = brak uprawnień; 4/17/32/613 = limity (ponów później).
        $invalid = $code === 190 || in_array((int) ($err['error_subcode'] ?? 0), [458, 459, 460, 463, 467], true);
        $retryable = ! $invalid && ! in_array($code, [10, 100, 200], true);

        throw new ApiException($msg, $invalid, $retryable);
    }
}
