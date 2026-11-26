<?php

namespace App\Services\Social;

use App\Enums\Platform;
use App\Enums\Role;
use App\Models\FileUsage;
use App\Models\Publication;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\PublicationFailed;
use App\Services\FileStore;
use App\Services\LicenseGuard;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Automatyczna publikacja zaplanowanych postów (cron co minutę) i pobieranie statystyk (cron co godzinę).
 * Publikujemy tylko, gdy: integracja platformy włączona, konto połączone, przy publikacji zaznaczone
 * „Publikuj automatycznie”, licencje materiałów pozwalają, a post pokazany klientowi jest przez niego zaakceptowany.
 */
class AutoPublisher
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(private MetaClient $meta, private TikTokClient $tiktok, private FileStore $files) {}

    /** Publikacje gotowe do automatycznej publikacji albo w trakcie. */
    public function due()
    {
        return Publication::with(['content.files.file', 'content.materials', 'account'])
            ->where('auto_publish', true)->where('status', 'planned')->where('scheduled_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('api_status')->orWhereIn('api_status', ['queued', 'processing', 'held'])
                ->orWhere(fn ($f) => $f->where('api_status', 'failed')->where('api_attempts', '<', self::MAX_ATTEMPTS)))
            ->where(fn ($q) => $q->whereNull('api_next_attempt_at')->orWhere('api_next_attempt_at', '<=', now()))
            ->orderBy('scheduled_at')->limit(10)->get();
    }

    public function runDue(): int
    {
        $n = 0;
        foreach ($this->due() as $pub) {
            $this->process($pub);
            $n++;
        }

        return $n;
    }

    public function process(Publication $pub): void
    {
        $acc = $pub->account;
        // Warunki, które wstrzymują publikację (bez liczenia prób).
        $hold = match (true) {
            ! Integrations::enabledFor($pub->platform) => __('api.hold_disabled'),
            ! $acc?->isConnected() => __('api.hold_not_connected'),
            $pub->visible_to_client && $pub->client_status !== 'approved' => __('api.hold_client'),
            default => null,
        };
        // Nie publikujemy „po cichu” z dużym opóźnieniem (np. akceptacja przyszła po 2 dniach) — decyduje zespół.
        if ($pub->api_status !== 'processing' && $pub->scheduled_at->lt(now()->subDay())) {
            $this->fail($pub, new ApiException(__('api.missed'), retryable: false));

            return;
        }
        $license = LicenseGuard::publishBlockers($pub->content->materials, $pub->scheduled_at->isPast() ? now() : $pub->scheduled_at);
        if ($hold || $license) {
            $this->hold($pub, $hold ?? implode(' ', $license));

            return;
        }

        try {
            match ($pub->platform) {
                Platform::Facebook => $this->facebook($pub),
                Platform::Instagram => $this->instagram($pub),
                Platform::TikTok => $this->tiktokPublish($pub),
                default => throw new ApiException(__('api.unsupported'), retryable: false),
            };
        } catch (ApiException $e) {
            $this->fail($pub, $e);
        } catch (\Throwable $e) {
            report($e);
            $this->fail($pub, new ApiException(Str::limit($e->getMessage(), 300)));
        }
    }

    // ---------------------------------------------------------------- platformy

    private function facebook(Publication $pub): void
    {
        [$images, $video] = $this->mediaUrls($pub);
        [$id, $link] = $this->meta->publishFacebook($pub->account, $this->caption($pub), $images, $video);
        $this->done($pub, $id, $link);
    }

    /** Instagram w krokach: kontener → (przetwarzanie filmu, kolejne uruchomienia crona) → publikacja. */
    private function instagram(Publication $pub): void
    {
        if (! $pub->api_container) {
            [$images, $video] = $this->mediaUrls($pub);
            $pub->forceFill(['api_container' => $this->meta->createInstagramContainer($pub->account, $this->caption($pub), $images, $video),
                'api_status' => 'processing', 'api_error' => null])->save();
        }
        $status = $this->meta->containerStatus($pub->account, $pub->api_container);
        if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
            $pub->forceFill(['api_container' => null])->save();
            throw new ApiException(__('api.ig_container_error', ['status' => $status]));
        }
        if ($status !== 'FINISHED') {
            $pub->forceFill(['api_status' => 'processing', 'api_next_attempt_at' => now()->addMinute()])->save();

            return;
        }
        [$id, $link] = $this->meta->publishInstagramContainer($pub->account, $pub->api_container);
        $this->done($pub, $id, $link);
    }

    private function tiktokPublish(Publication $pub): void
    {
        if (! $pub->api_container) {
            $video = $pub->content->files->first(fn (FileUsage $f) => $f->file?->isVideo());
            if (! $video || $video->file->location !== 'hosting') {
                throw new ApiException(__('api.tiktok_needs_video'), retryable: false);
            }
            $publishId = $this->tiktok->publishVideo($pub->account, $this->files->absolutePath($video->file), $this->caption($pub));
            $pub->forceFill(['api_container' => $publishId, 'api_status' => 'processing', 'api_error' => null, 'api_next_attempt_at' => now()->addMinute()])->save();

            return;
        }
        [$status, $postId, $reason] = $this->tiktok->publishStatus($pub->account, $pub->api_container);
        if ($status === 'FAILED') {
            $pub->forceFill(['api_container' => null])->save();
            throw new ApiException('TikTok: '.($reason ?: 'FAILED'));
        }
        if ($status === 'PUBLISH_COMPLETE') {
            $handle = ltrim((string) ($pub->account->api_meta['username'] ?? $pub->account->handle), '@');
            $this->done($pub, $postId ?? $pub->api_container, $postId && $handle ? "https://www.tiktok.com/@{$handle}/video/{$postId}" : null);

            return;
        }
        $pub->forceFill(['api_status' => 'processing', 'api_next_attempt_at' => now()->addMinute()])->save();
    }

    // ---------------------------------------------------------------- pomocnicze

    /** Treść posta: opis pod platformę albo opis treści + hashtagi. */
    public function caption(Publication $pub): string
    {
        $text = $pub->caption ?: trim(($pub->content->description ?? '')."\n\n".($pub->content->hashtags ?? ''));

        return trim($text) !== '' ? trim($text) : $pub->content->title;
    }

    /**
     * Pliki treści jako krótko ważne, podpisane adresy (Meta pobiera media z adresu URL).
     * Działają tylko dla plików treści i wygasają po 2 godzinach.
     */
    public function mediaUrls(Publication $pub): array
    {
        $files = $pub->content->files->filter(fn (FileUsage $f) => $f->file && $f->file->location === 'hosting');
        $video = $files->first(fn ($f) => $f->file->isVideo());
        $sign = fn (FileUsage $f) => URL::temporarySignedRoute('media.public', now()->addHours(2), ['usage' => $f->id, 'name' => $f->name]);
        if ($video) {
            return [[], $sign($video)];
        }

        return [$files->filter(fn ($f) => $f->file->isImage() && $f->file->mime !== 'image/svg+xml')->map($sign)->values()->all(), null];
    }

    private function done(Publication $pub, string $id, ?string $link): void
    {
        $pub->forceFill(['status' => 'published', 'published_at' => now(), 'url' => $link ?: $pub->url, 'external_id' => $id,
            'api_status' => 'published', 'api_error' => null, 'api_container' => null, 'api_next_attempt_at' => null])->save();
        SystemLog::record('social_publish', "Published #{$pub->id} on {$pub->platform->value}", ['id' => $id]);
    }

    private function hold(Publication $pub, string $reason): void
    {
        $first = $pub->api_status !== 'held';
        $pub->forceFill(['api_status' => 'held', 'api_error' => Str::limit($reason, 490), 'api_next_attempt_at' => now()->addMinutes(10)])->save();
        if ($first) {
            $this->notify($pub, $reason);
        }
    }

    private function fail(Publication $pub, ApiException $e): void
    {
        $attempts = $pub->api_attempts + 1;
        $final = ! $e->retryable || $attempts >= self::MAX_ATTEMPTS;
        if ($e->tokenInvalid) {
            $pub->account->disconnect(Str::limit($e->getMessage(), 250));
        }
        $pub->forceFill(['api_status' => 'failed', 'api_error' => Str::limit($e->getMessage(), 490), 'api_attempts' => $final ? self::MAX_ATTEMPTS : $attempts,
            'api_next_attempt_at' => $final ? null : now()->addMinutes(5 * $attempts)])->save();
        SystemLog::record('social_publish', "Publish #{$pub->id} failed: ".$e->getMessage(), [], $final ? 'error' : 'warning');
        if ($final) {
            $this->notify($pub, $e->getMessage());
        }
    }

    private function notify(Publication $pub, string $reason): void
    {
        Notification::send(User::where('role', Role::Admin)->where('is_active', true)->get(), new PublicationFailed($pub, $reason));
    }

    // ---------------------------------------------------------------- statystyki

    /** Pomiary 1/7/30 dni po publikacji dla połączonych kont — zamiast ręcznego wpisywania. */
    public function fetchStats(): int
    {
        if (! Integrations::autoStats()) {
            return 0;
        }
        $n = 0;
        $due = \App\Services\SocialStats::statsQueue()->filter(fn (Publication $p) => $p->external_id && $p->account?->isConnected());
        foreach ($due->take(50) as $pub) {
            try {
                $s = match ($pub->platform) {
                    Platform::Facebook => $this->meta->facebookStats($pub->account, $pub->external_id),
                    Platform::Instagram => $this->meta->instagramStats($pub->account, $pub->external_id, $pub->content->format === 'video'),
                    Platform::TikTok => $this->tiktok->videoStats($pub->account, $pub->external_id),
                    default => null,
                };
                if ($s === null) {
                    continue;
                }
                $pub->measurements()->create(['measured_on' => today(), 'source' => 'api', 'reach' => (int) ($s['reach'] ?? 0), 'reactions' => (int) ($s['reactions'] ?? 0),
                    'comments' => (int) ($s['comments'] ?? 0), 'shares' => (int) ($s['shares'] ?? 0), 'clicks' => (int) ($s['clicks'] ?? 0), 'views' => $s['views'] ?? null]);
                $pub->increment('checks_done');
                $n++;
            } catch (ApiException $e) {
                if ($e->tokenInvalid) {
                    $pub->account->disconnect(Str::limit($e->getMessage(), 250));
                }
                SystemLog::record('social_stats', "Stats #{$pub->id}: ".$e->getMessage(), [], 'warning');
            }
        }

        return $n;
    }
}
