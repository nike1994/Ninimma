<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AdCampaign;
use App\Models\LicenseExtension;
use App\Models\Material;
use App\Models\Publication;
use App\Models\User;
use App\Notifications\LicenseAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Strażnik licencji materiałów (influencerzy / UGC):
 * pilnuje dat i kanałów użycia w planie publikacji, promocjach i reklamach.
 */
class LicenseGuard
{
    public const EXPIRING_DAYS = 14;

    public const ALERT_THRESHOLDS = [30, 14, 7, 1, 0];

    /** Stan licencji: none | upcoming | active | expiring | expired + liczniki do timera. */
    public static function status(Material $m): object
    {
        $from = $m->license_from;
        $until = $m->license_until;
        if (! $until) {
            return (object) ['state' => 'none', 'days_left' => null, 'pct_left' => null, 'total' => null];
        }
        $today = today();
        $daysLeft = (int) $today->diffInDays($until, false);
        $start = $from ?? $m->created_at?->copy()->startOfDay() ?? $today;
        $total = max(1, (int) $start->diffInDays($until));
        $state = match (true) {
            $from !== null && $from->isAfter($today) => 'upcoming',
            $daysLeft < 0 => 'expired',
            $daysLeft <= self::EXPIRING_DAYS => 'expiring',
            default => 'active',
        };

        return (object) [
            'state' => $state,
            'days_left' => $daysLeft,
            'total' => $total,
            'pct_left' => $state === 'upcoming' ? 100 : max(0, min(100, (int) round($daysLeft / $total * 100))),
            'starts_in' => $from && $from->isAfter($today) ? (int) $today->diffInDays($from) : null,
        ];
    }

    /** Publikacje, w których użyto materiału (przez treść albo bezpośrednie powiązanie). */
    private function publicationsOf(Material $m): Collection
    {
        $contentIds = $m->relationLoaded('contents') ? $m->contents->pluck('id') : $m->contents()->pluck('contents.id');

        return Publication::with(['content', 'account', 'ad'])->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereIn('content_id', $contentIds)->when($m->publication_id, fn ($w) => $w->orWhere('id', $m->publication_id)))
            ->get();
    }

    private function adsOf(Material $m, Collection $pubs): Collection
    {
        return AdCampaign::with('publication.content')->whereIn('status', ['planned', 'active', 'paused'])
            ->where(fn ($q) => $q->where('material_id', $m->id)->orWhereIn('publication_id', $pubs->pluck('id')))->get();
    }

    private static function issue(string $type, string $severity, Material $m, string $subject, string $url, array $params = [], array $ref = []): object
    {
        return (object) ($ref + ['publication_id' => null, 'ad_id' => null, 'content_id' => null] + ['type' => $type, 'severity' => $severity, 'material' => $m, 'subject' => $subject, 'url' => $url, 'params' => $params,
            'message' => __('lic.issue_'.$type, $params + ['material' => $m->description, 'subject' => $subject])]);
    }

    /** Konflikty jednego materiału. */
    public function issuesFor(Material $m): Collection
    {
        $issues = collect();
        $until = $m->license_until;
        $from = $m->license_from;
        $expired = $until && $until->isBefore(today());
        $pubs = $this->publicationsOf($m);

        foreach ($pubs as $p) {
            $label = $p->content->title.' · '.$p->platform->label().' '.$p->scheduled_at->format('d.m');
            $url = route('social.contents.edit', $p->content_id);
            $ref = ['publication_id' => $p->id, 'content_id' => $p->content_id];
            if ($p->status === 'planned') {
                if ($until && $p->scheduled_at->copy()->startOfDay()->isAfter($until)) {
                    $issues->push(self::issue('publish_after_expiry', 'error', $m, $label, $url, ['until' => $until->format('d.m.Y')], $ref));
                }
                if ($from && $p->scheduled_at->copy()->startOfDay()->isBefore($from)) {
                    $issues->push(self::issue('publish_before_start', 'error', $m, $label, $url, ['from' => $from->format('d.m.Y')], $ref));
                }
                if (! $m->allows('organic')) {
                    $issues->push(self::issue('channel_organic', 'error', $m, $label, $url, [], $ref));
                }
            }
            if ($p->status === 'published' && $expired && $m->requires_takedown) {
                $issues->push(self::issue('takedown', 'error', $m, $label, $p->url ?: $url, ['until' => $until->format('d.m.Y')], $ref));
            }
        }

        foreach ($this->adsOf($m, $pubs) as $ad) {
            $url = route('social.ads.edit', $ad);
            $ref = ['ad_id' => $ad->id, 'publication_id' => $ad->publication_id, 'content_id' => $ad->publication?->content_id];
            if (! $m->allows('paid')) {
                $issues->push(self::issue('channel_paid', 'error', $m, $ad->name, $url, [], $ref));
            }
            if ($until && $ad->status === 'active' && $expired) {
                $issues->push(self::issue('ad_running_expired', 'error', $m, $ad->name, $url, ['until' => $until->format('d.m.Y')], $ref));
            } elseif ($until && (! $ad->ends_on || $ad->ends_on->isAfter($until))) {
                $issues->push(self::issue('ad_beyond_license', 'warning', $m, $ad->name, $url, ['until' => $until->format('d.m.Y'),
                    'ends' => $ad->ends_on?->format('d.m.Y') ?? '∞'], $ref));
            }
            if ($from && $ad->starts_on && $ad->starts_on->isBefore($from)) {
                $issues->push(self::issue('ad_before_start', 'error', $m, $ad->name, $url, ['from' => $from->format('d.m.Y')], $ref));
            }
        }

        return $issues;
    }

    /** Wszystkie konflikty (materiały z datami lub ograniczeniami kanałów). */
    public function allIssues(?int $clientId = null): Collection
    {
        return Material::with(['collaboration.influencer', 'collaboration.campaign', 'contents'])
            ->where(fn ($q) => $q->whereNotNull('license_until')->orWhereNotNull('license_from')->orWhereNotNull('license_channels'))
            ->when($clientId, fn ($q) => $q->whereHas('collaboration.campaign', fn ($c) => $c->where('client_id', $clientId)))
            ->get()->flatMap(fn ($m) => $this->issuesFor($m))
            ->sortBy(fn ($i) => $i->severity === 'error' ? 0 : 1)->values();
    }

    /** Konflikty według publikacji (znaczniki w kalendarzach): [publication_id => [komunikaty]]. */
    public function issuesByPublication(): Collection
    {
        return $this->allIssues()->whereNotNull('publication_id')->groupBy('publication_id')->map(fn ($g) => $g->pluck('message')->unique()->values());
    }

    /** Konflikty według reklamy: [ad_id => [komunikaty]]. */
    public function issuesByAd(): Collection
    {
        return $this->allIssues()->whereNotNull('ad_id')->groupBy('ad_id')->map(fn ($g) => $g->pluck('message')->unique()->values());
    }

    /** Konflikty materiałów użytych w treści (formularz treści). */
    public function issuesForContent(\App\Models\Content $content): Collection
    {
        return $content->materials()->get()->flatMap(fn ($m) => $this->issuesFor($m))
            ->filter(fn ($i) => $i->content_id === $content->id)->values();
    }

    /** Materiały do wyboru w treściach i reklamach (otrzymane, aktywnych współprac). */
    public static function pickerMaterials(array $keep = []): Collection
    {
        return Material::with(['collaboration.influencer', 'collaboration.campaign'])
            ->where(fn ($q) => $q->where('is_received', true)->whereHas('collaboration', fn ($c) => $c->where('status', '!=', 'cancelled'))
                ->orWhereIn('id', $keep))
            ->get()->sortBy(fn ($m) => [$m->collaboration->campaign->name, $m->description])->values();
    }

    public function extend(Material $m, User $by, string $until, ?array $channels, ?float $cost, ?string $note): LicenseExtension
    {
        return DB::transaction(function () use ($m, $by, $until, $channels, $cost, $note) {
            $ext = $m->extensions()->create(['user_id' => $by->id, 'previous_until' => $m->license_until, 'new_until' => $until,
                'channels' => $channels, 'cost' => $cost, 'note' => $note]);
            $m->update(['license_until' => $until] + ($channels !== null ? ['license_channels' => $channels] : []));

            return $ext;
        });
    }

    /** Cron: e-mail do zespołu przy progach 30/14/7/1/0 dni (każdy próg raz) + bieżące konflikty. */
    public function sendAlerts(): int
    {
        $items = collect();
        Material::with('collaboration.influencer', 'collaboration.campaign')->whereNotNull('license_until')
            ->whereDate('license_until', '>=', today())->whereDate('license_until', '<=', today()->addDays(max(self::ALERT_THRESHOLDS)))
            ->get()->each(function (Material $m) use ($items) {
                $left = self::status($m)->days_left;
                $threshold = collect(self::ALERT_THRESHOLDS)->sort()->first(fn ($t) => $left <= $t);
                if ($threshold === null) {
                    return;
                }
                $inserted = DB::table('license_alerts')->insertOrIgnore(['material_id' => $m->id, 'license_until' => $m->license_until->toDateString(), 'threshold' => $threshold, 'created_at' => now()]);
                if ($inserted) {
                    $items->push(['material' => $m->description, 'who' => $m->collaboration->influencer->name, 'campaign' => $m->collaboration->campaign->name,
                        'until' => $m->license_until->format('d.m.Y'), 'days' => $left]);
                }
            });
        $conflicts = $this->allIssues()->where('severity', 'error')->count();
        if ($items->isNotEmpty()) {
            Notification::send(User::where('role', Role::Admin)->where('is_active', true)->get(), new LicenseAlert($items, $conflicts));
        }

        return $items->count();
    }
}
