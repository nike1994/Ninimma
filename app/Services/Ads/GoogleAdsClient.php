<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Services\Social\ApiException;
use App\Services\Social\Integrations;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Ads API: wyniki kampanii zapytaniem GAQL (searchStream). ID przy reklamie: „ID klienta:ID kampanii”,
 * np. 123-456-7890:2233445566. Google nie podaje zasięgu dla kampanii w wyszukiwarce — zostaje 0.
 */
class GoogleAdsClient
{
    public static function parseId(string $externalId): ?array
    {
        if (! preg_match('/^\s*([\d-]{10,12})\s*[:\/]\s*(\d+)\s*$/', $externalId, $m)) {
            return null;
        }

        return [str_replace('-', '', $m[1]), $m[2]];
    }

    public function adTotals(AdCampaign $ad): ?array
    {
        $ids = self::parseId((string) $ad->external_id);
        if (! $ids) {
            throw new ApiException(__('ads.bad_id_google'), false, false);
        }
        [$customer, $campaign] = $ids;
        $where = 'campaign.id = '.$campaign;
        if ($ad->starts_on) {
            $where .= " AND segments.date BETWEEN '".$ad->starts_on->format('Y-m-d')."' AND '".today()->format('Y-m-d')."'";
        }
        $query = 'SELECT metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value FROM campaign WHERE '.$where;
        $cfg = Integrations::googleAds();
        $headers = ['developer-token' => $cfg['developer_token']];
        if (filled($cfg['login_customer_id'])) {
            $headers['login-customer-id'] = str_replace('-', '', $cfg['login_customer_id']);
        }
        $r = Http::timeout(30)->withToken($this->accessToken())->withHeaders($headers)
            ->post("https://googleads.googleapis.com/{$cfg['version']}/customers/{$customer}/googleAds:searchStream", ['query' => $query]);
        $batches = $this->check($r);
        $rows = collect($batches)->flatMap(fn ($b) => $b['results'] ?? []);
        if ($rows->isEmpty()) {
            return null;
        }
        $sum = fn ($k) => $rows->sum(fn ($row) => (float) ($row['metrics'][$k] ?? 0));

        return [
            'spend' => round($sum('costMicros') / 1_000_000, 2), 'impressions' => (int) $sum('impressions'), 'reach' => 0,
            'clicks' => (int) $sum('clicks'), 'conversions' => (int) round($sum('conversions')), 'revenue' => round($sum('conversionsValue'), 2),
        ];
    }

    /** Token dostępu z tokenu odświeżania (ważny godzinę; trzymamy 50 minut). */
    private function accessToken(): string
    {
        return Cache::remember('gads_access_token', 3000, function () {
            $cfg = Integrations::googleAds();
            $json = $this->check(Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'refresh_token', 'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'], 'refresh_token' => $cfg['refresh_token'],
            ]));

            return $json['access_token'] ?? throw new ApiException('Google OAuth: brak access_token', true, false);
        });
    }

    private function check(Response $r): array
    {
        $json = $r->json() ?? [];
        if ($r->successful()) {
            return $json;
        }
        $err = $json[0]['error'] ?? $json['error'] ?? [];
        $msg = is_array($err) ? ($err['details'][0]['errors'][0]['message'] ?? $err['message'] ?? 'HTTP '.$r->status()) : ($json['error_description'] ?? (string) $err);
        if ($r->status() === 401) {
            Cache::forget('gads_access_token');
        }

        throw new ApiException(mb_substr('Google Ads: '.$msg, 0, 280), $r->status() === 401, $r->status() >= 500 || $r->status() === 429);
    }
}
