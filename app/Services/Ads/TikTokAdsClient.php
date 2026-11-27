<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Services\Social\ApiException;
use App\Services\Social\Integrations;
use Illuminate\Support\Facades\Http;

/**
 * TikTok Marketing API (raport zintegrowany): wyniki kampanii za cały okres.
 * ID przy reklamie: „ID konta reklamowego:ID kampanii”, np. 7012345678901234567:1789012345678901.
 */
class TikTokAdsClient
{
    public const BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    public static function parseId(string $externalId): ?array
    {
        return preg_match('/^\s*(\d{6,})\s*[:\/]\s*(\d{6,})\s*$/', $externalId, $m) ? [$m[1], $m[2]] : null;
    }

    public function adTotals(AdCampaign $ad): ?array
    {
        $ids = self::parseId((string) $ad->external_id);
        if (! $ids) {
            throw new ApiException(__('ads.bad_id_tiktok'), false, false);
        }
        [$advertiser, $campaign] = $ids;
        $r = Http::timeout(30)->withHeaders(['Access-Token' => Integrations::encrypted('int_ttads_token')])->get(self::BASE.'report/integrated/get/', [
            'advertiser_id' => $advertiser, 'report_type' => 'BASIC', 'data_level' => 'AUCTION_CAMPAIGN', 'query_lifetime' => 'true',
            'dimensions' => json_encode(['campaign_id']),
            'metrics' => json_encode(['spend', 'impressions', 'reach', 'clicks', 'conversion', 'complete_payment', 'value_per_complete_payment']),
            'filtering' => json_encode([['field_name' => 'campaign_ids', 'filter_type' => 'IN', 'filter_value' => json_encode([$campaign])]]),
        ]);
        $json = $r->json() ?? [];
        if (! $r->successful() || (int) ($json['code'] ?? -1) !== 0) {
            $code = (int) ($json['code'] ?? 0);
            throw new ApiException(mb_substr('TikTok Ads: '.($json['message'] ?? 'HTTP '.$r->status()).' ('.$code.')', 0, 280), in_array($code, [40001, 40100, 40105], true), $code === 40100 || $r->status() >= 500);
        }
        $m = $json['data']['list'][0]['metrics'] ?? null;
        if (! $m) {
            return null;
        }
        $payments = (int) ($m['complete_payment'] ?? 0);

        return [
            'spend' => round((float) ($m['spend'] ?? 0), 2), 'impressions' => (int) ($m['impressions'] ?? 0), 'reach' => (int) ($m['reach'] ?? 0),
            'clicks' => (int) ($m['clicks'] ?? 0), 'conversions' => $payments ?: (int) ($m['conversion'] ?? 0),
            'revenue' => round($payments * (float) ($m['value_per_complete_payment'] ?? 0), 2),
        ];
    }
}
