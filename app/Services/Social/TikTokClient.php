<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * TikTok: Login Kit + Content Posting API (bezpośrednia publikacja filmu, wysyłka w kawałkach z serwera)
 * + Display API (statystyki filmów). Zakresy: user.info.basic, video.publish, video.list.
 */
class TikTokClient
{
    public const SCOPES = ['user.info.basic', 'video.publish', 'video.list'];

    private const API = 'https://open.tiktokapis.com/v2/';

    public function authorizeUrl(string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => Integrations::key('tiktok'), 'scope' => implode(',', self::SCOPES), 'response_type' => 'code',
            'redirect_uri' => Integrations::redirectUri('tiktok'), 'state' => $state,
        ]);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int, open_id: string} */
    public function exchangeCode(string $code): array
    {
        return $this->token(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => Integrations::redirectUri('tiktok')]);
    }

    public function userInfo(string $token): array
    {
        return $this->check(Http::withToken($token)->timeout(20)->get(self::API.'user/info/', ['fields' => 'open_id,display_name,username,avatar_large_url']))['data']['user'] ?? [];
    }

    /** Token dostępu ważny 24 h — odświeżamy przed użyciem. */
    public function ensureToken(SocialAccount $acc): string
    {
        if ($acc->token_expires_at && $acc->token_expires_at->isAfter(now()->addMinutes(10))) {
            return $acc->access_token;
        }
        if (! $acc->refresh_token) {
            throw new ApiException(__('api.reconnect_needed'), tokenInvalid: true);
        }
        $t = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $acc->refresh_token]);
        $acc->forceFill(['access_token' => $t['access_token'], 'refresh_token' => $t['refresh_token'] ?? $acc->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $t['expires_in'])])->save();

        return $t['access_token'];
    }

    /** Wysyłka filmu i start publikacji. Zwraca publish_id (publikacja kończy się asynchronicznie). */
    public function publishVideo(SocialAccount $acc, string $path, string $caption): string
    {
        $token = $this->ensureToken($acc);
        $info = $this->check(Http::withToken($token)->timeout(20)->post(self::API.'post/publish/creator_info/query/', (object) []))['data'] ?? [];
        $wanted = Integrations::tiktokPrivacy();
        $options = $info['privacy_level_options'] ?? [$wanted];
        $privacy = in_array($wanted, $options, true) ? $wanted : ($options[0] ?? 'SELF_ONLY');

        $size = filesize($path);
        // Kawałki 5–64 MB; mniejszy plik idzie w całości, ostatni kawałek bierze resztę.
        $chunk = $size <= 64 * 1024 * 1024 ? $size : 10 * 1024 * 1024;
        $count = max(1, intdiv($size, $chunk));
        $init = $this->check(Http::withToken($token)->timeout(30)->post(self::API.'post/publish/video/init/', [
            'post_info' => ['title' => mb_substr($caption, 0, 2200), 'privacy_level' => $privacy, 'disable_comment' => false,
                'disable_duet' => false, 'disable_stitch' => false],
            'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $chunk, 'total_chunk_count' => $count],
        ]))['data'];

        $fh = fopen($path, 'rb');
        try {
            for ($i = 0; $i < $count; $i++) {
                $start = $i * $chunk;
                $len = $i === $count - 1 ? $size - $start : $chunk;
                fseek($fh, $start);
                $body = fread($fh, $len);
                $r = Http::timeout(300)->withHeaders(['Content-Range' => 'bytes '.$start.'-'.($start + $len - 1).'/'.$size, 'Content-Type' => 'video/mp4'])
                    ->withBody($body, 'video/mp4')->put($init['upload_url']);
                if (! $r->successful()) {
                    throw new ApiException('TikTok upload HTTP '.$r->status());
                }
            }
        } finally {
            fclose($fh);
        }

        return $init['publish_id'];
    }

    /** Stan publikacji: [status, id posta|null, powód błędu|null]. */
    public function publishStatus(SocialAccount $acc, string $publishId): array
    {
        $d = $this->check(Http::withToken($this->ensureToken($acc))->timeout(20)->post(self::API.'post/publish/status/fetch/', ['publish_id' => $publishId]))['data'] ?? [];

        return [$d['status'] ?? 'PROCESSING_UPLOAD', $d['publicaly_available_post_id'][0] ?? null, $d['fail_reason'] ?? null];
    }

    public function videoStats(SocialAccount $acc, string $videoId): array
    {
        $v = $this->check(Http::withToken($this->ensureToken($acc))->timeout(20)
            ->post(self::API.'video/query/?fields=id,view_count,like_count,comment_count,share_count,share_url', ['filters' => ['video_ids' => [$videoId]]]))['data']['videos'][0] ?? [];

        return ['reach' => $v['view_count'] ?? null, 'views' => $v['view_count'] ?? null, 'reactions' => $v['like_count'] ?? null,
            'comments' => $v['comment_count'] ?? null, 'shares' => $v['share_count'] ?? null, 'clicks' => null, 'url' => $v['share_url'] ?? null];
    }

    // ----------------------------------------------------------------

    private function token(array $params): array
    {
        $r = Http::asForm()->timeout(20)->post(self::API.'oauth/token/', $params + [
            'client_key' => Integrations::key('tiktok'), 'client_secret' => Integrations::secret('tiktok'),
        ]);
        $j = $r->json() ?? [];
        if (! $r->successful() || empty($j['access_token'])) {
            throw new ApiException(($j['error_description'] ?? $j['error'] ?? 'HTTP '.$r->status()).' (TikTok)', tokenInvalid: ($j['error'] ?? '') === 'invalid_grant');
        }

        return $j;
    }

    private function check(Response $r): array
    {
        $j = $r->json() ?? [];
        $code = $j['error']['code'] ?? ($r->successful() ? 'ok' : 'http_'.$r->status());
        if ($r->successful() && $code === 'ok') {
            return $j;
        }
        $msg = ($j['error']['message'] ?? 'HTTP '.$r->status()).' (TikTok '.$code.')';
        $invalid = in_array($code, ['access_token_invalid', 'scope_not_authorized', 'token_not_authorized_for_specified_scope'], true);
        $retryable = ! $invalid && ! in_array($code, ['spam_risk_too_many_posts', 'unaudited_client_can_only_post_to_private_accounts', 'privacy_level_option_mismatch', 'invalid_params'], true);

        throw new ApiException($msg, $invalid, $retryable);
    }
}
