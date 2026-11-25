<?php

namespace Database\Seeders;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\UtmLink;
use App\Services\AdService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Demo reklam i promocji (tylko lokalnie). */
class AdsDemoSeeder extends Seeder
{
    public function run(AdService $ads): void
    {
        $now = Carbon::now();
        mt_srand(11);
        // Promowane posty z ostatnich tygodni.
        foreach (Publication::where('status', 'published')->latest('published_at')->limit(4)->get() as $k => $pub) {
            $pub->forceFill(['promoted' => true, 'promo_budget' => [500, 800, 300, 600][$k],
                'promo_from' => $pub->published_at->toDateString(), 'promo_to' => $pub->published_at->copy()->addDays([10, 14, 5, 7][$k])->toDateString()])->save();
            $ads->syncFromPublication($pub->fresh());
        }
        $c = Campaign::orderBy('id')->get();
        foreach ([
            [$c[0], 'google', 'Search — rośliny doniczkowe', 'conversions', 2000, -12, 18],
            [$c[0], 'meta', 'Remarketing — porzucone koszyki', 'conversions', 1200, -8, 2],
            [$c[1], 'meta', 'Zasięg — nowa kolekcja', 'reach', 1500, 3, 25],
            [$c[2], 'meta', 'Leady — zapisy na sezon', 'leads', 1000, -15, 10],
            [$c[2], 'tiktok', 'TikTok — kulisy sesji', 'video_views', 700, -30, -5],
        ] as [$cmp, $platform, $name, $objective, $budget, $start, $end]) {
            AdCampaign::create(['client_id' => $cmp->client_id, 'campaign_id' => $cmp->id, 'platform' => $platform, 'name' => $name,
                'external_name' => \Illuminate\Support\Str::slug($name, '_'), 'objective' => $objective, 'budget' => $budget,
                'starts_on' => $now->copy()->addDays($start)->toDateString(), 'ends_on' => $now->copy()->addDays($end)->toDateString(),
                'target_url' => 'https://example.com/?utm_source='.$platform.'&utm_medium=cpc&utm_campaign='.\Illuminate\Support\Str::slug($cmp->name, '_')]);
        }
        $ads->updateStatuses();
        // Pomiary narastające.
        foreach (AdCampaign::all() as $ad) {
            if (! $ad->starts_on || $ad->starts_on->isFuture()) {
                continue;
            }
            $days = max(1, (int) $ad->starts_on->diffInDays(min($now, $ad->ends_on ?? $now)));
            $daily = (float) $ad->budget / max(1, $ad->starts_on->diffInDays($ad->ends_on ?? $now->copy()->addDays(10)));
            foreach ([0.33, 0.66, 1] as $f) {
                $spend = round($daily * $days * $f * mt_rand(85, 105) / 100, 2);
                $impr = (int) ($spend * mt_rand(90, 160));
                $clicks = (int) ($impr * mt_rand(8, 25) / 1000);
                $conv = in_array($ad->objective, ['conversions', 'leads'], true) ? (int) ($clicks * mt_rand(2, 6) / 100) : 0;
                $ads->addResult($ad, null, [
                    'measured_on' => $ad->starts_on->copy()->addDays((int) round($days * $f))->toDateString(),
                    'spend' => $spend, 'impressions' => $impr, 'reach' => (int) ($impr * 0.7), 'clicks' => $clicks,
                    'conversions' => $ad->objective === 'conversions' ? $conv : 0, 'leads' => $ad->objective === 'leads' ? $conv : 0,
                    'revenue' => $ad->objective === 'conversions' ? $conv * mt_rand(120, 260) : 0,
                ]);
            }
        }
        foreach ([['facebook', 'paid_social', 'karuzela'], ['instagram', 'influencer', 'ola'], ['newsletter', 'email', null]] as [$src, $med, $content]) {
            UtmLink::create(['campaign_id' => $c[0]->id, 'base_url' => 'https://zielonydom.pl/black-friday', 'source' => $src, 'medium' => $med,
                'name' => 'black_friday', 'content' => $content, 'full_url' => UtmLink::build('https://zielonydom.pl/black-friday', ['source' => $src, 'medium' => $med, 'name' => 'black_friday', 'content' => $content])]);
        }
    }
}
