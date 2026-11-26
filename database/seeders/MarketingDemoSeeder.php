<?php

namespace Database\Seeders;

use App\Models\AdCampaign;
use App\Models\AdResult;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Content;
use App\Services\AdService;
use Illuminate\Database\Seeder;

/** Demo kampanii marketingowych: cele, statusy, lejek w wynikach reklam, odbiorcy (płeć, wiek, miasta, kraje). */
class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);
        $today = today();
        $goals = [
            'Black Friday – Zielony Dom' => 'Zwiększenie sprzedaży donic i roślin w tygodniu Black Friday oraz ruchu na stronie sklepu.',
            'Świąteczna sprzedaż – FitLife' => 'Sprzedaż zestawów prezentowych i karnetów przed świętami; 300 zamówień ze źródła social.',
            'Zapisy na nowy sezon – Studio Blu' => 'Pozyskanie 120 zapisów na zajęcia w nowym sezonie (leady z formularza).',
        ];
        foreach (Campaign::all() as $c) {
            $c->update(['goal' => $goals[$c->name] ?? $c->goal]);
        }
        Campaign::where('name', 'Świąteczna sprzedaż – FitLife')->update(['status' => 'active']);

        // Kampanie zakończone (z wynikami) i jedna planowana.
        $add = [
            ['Zielony Dom', 'Jesienne aranżacje – Zielony Dom', 'finished', -60, -30, 2500, 'Rozpoznawalność nowej kolekcji jesiennej i zapisy do newslettera.'],
            ['Studio Blu', 'Rekrutacja instruktorów – Studio Blu', 'finished', -75, -45, 1200, 'Zebranie 40 zgłoszeń od instruktorów (leady).'],
            ['FitLife', 'Nowa kolekcja – FitLife', 'review', 10, 40, 4000, 'Premiera nowej linii odzieży sportowej — zasięg i ruch na stronie.'],
        ];
        $ads = app(AdService::class);
        foreach ($add as [$client, $name, $status, $from, $to, $budget, $goal]) {
            $cl = Client::where('name', $client)->first();
            $c = Campaign::create(['client_id' => $cl->id, 'name' => $name, 'status' => $status, 'goal' => $goal, 'budget' => $budget,
                'starts_on' => $today->copy()->addDays($from), 'ends_on' => $today->copy()->addDays($to)]);
            if ($status === 'finished') {
                // Starsze opublikowane treści klienta trafiają do zakończonej kampanii.
                Content::where('client_id', $cl->id)->whereNull('campaign_id')->whereHas('publications', fn ($q) => $q->where('status', 'published'))
                    ->limit(2)->update(['campaign_id' => $c->id]);
                $ad = AdCampaign::create(['client_id' => $cl->id, 'campaign_id' => $c->id, 'platform' => 'meta', 'name' => $name.' — konwersje',
                    'objective' => $client === 'Studio Blu' ? 'leads' : 'conversions', 'budget' => $budget * .8, 'status' => 'finished',
                    'starts_on' => $today->copy()->addDays($from)->toDateString(), 'ends_on' => $today->copy()->addDays($to)->toDateString()]);
                foreach ([.3, .65, 1] as $k => $f) {
                    $spend = round($budget * .78 * $f, 2);
                    $impr = (int) ($spend * 140);
                    $clicks = (int) ($impr * .021);
                    $conv = (int) ($clicks * .016);
                    $ads->addResult($ad, null, ['measured_on' => $today->copy()->addDays($from + (int) (($to - $from) * $f))->toDateString(), 'spend' => $spend,
                        'impressions' => $impr, 'reach' => (int) ($impr * .68), 'clicks' => $clicks,
                        'conversions' => $client === 'Studio Blu' ? 0 : $conv, 'leads' => $client === 'Studio Blu' ? $conv : 0,
                        'revenue' => $client === 'Studio Blu' ? 0 : $conv * 129]);
                }
            }
        }

        // Lejek w wynikach reklam: wejścia na stronę i dodania do koszyka.
        foreach (AdResult::all() as $r) {
            $lv = (int) round($r->clicks * (mt_rand(68, 84) / 100));
            $r->update(['landing_views' => $lv, 'add_to_cart' => $r->conversions > 0 ? max($r->conversions, (int) round($lv * (mt_rand(14, 22) / 100))) : 0]);
        }

        // Rodzaj celu i wartość docelowa (wskaźniki, lejek i ranking zależą od celu).
        $targets = [
            'Black Friday – Zielony Dom' => ['sales', 90000], 'Świąteczna sprzedaż – FitLife' => ['sales', 40000],
            'Zapisy na nowy sezon – Studio Blu' => ['leads', 120], 'Jesienne aranżacje – Zielony Dom' => ['awareness', 150000],
            'Rekrutacja instruktorów – Studio Blu' => ['leads', 40], 'Nowa kolekcja – FitLife' => ['traffic', 5000],
        ];
        foreach ($targets as $name => [$objective, $target]) {
            Campaign::where('name', $name)->update(['objective' => $objective, 'target_value' => $target]);
        }

        // Świąteczna sprzedaż: reklama konwersji wydająca budżet za szybko (pokaz alertu tempa).
        $xmas = Campaign::where('name', 'Świąteczna sprzedaż – FitLife')->first();
        if ($xmas) {
            $ad = AdCampaign::create(['client_id' => $xmas->client_id, 'campaign_id' => $xmas->id, 'platform' => 'meta', 'name' => 'Zestawy prezentowe — konwersje',
                'objective' => 'conversions', 'budget' => 3000, 'starts_on' => $xmas->starts_on, 'ends_on' => $xmas->ends_on, 'status' => 'active']);
            foreach ([[3, 640, 52000, 24000, 610, 380, 70, 14], [7, 1420, 118000, 51000, 1390, 880, 160, 33], [10, 2600, 171000, 69000, 2010, 1290, 236, 49]] as [$d, $sp, $im, $re, $cl, $lv, $atc, $cv]) {
                $ad->results()->create(['measured_on' => $xmas->starts_on->copy()->addDays($d), 'spend' => $sp, 'impressions' => $im, 'reach' => $re, 'clicks' => $cl,
                    'landing_views' => $lv, 'add_to_cart' => $atc, 'conversions' => $cv, 'revenue' => $cv * 159]);
            }
        }

        // Odbiorcy kampanii.
        $cities = ['Warszawa' => 18.4, 'Kraków' => 10.8, 'Wrocław' => 7.9, 'Poznań' => 6.3, 'Gdańsk' => 5.4, 'Łódź' => 4.6, 'Katowice' => 3.9, 'Lublin' => 2.8];
        foreach (Campaign::all() as $c) {
            $f = mt_rand(52, 71);
            $rows = [['gender', 'female', $f], ['gender', 'male', 100 - $f]];
            $ages = [8, 28, 24, 20, 12, 8];
            foreach (\App\Models\CampaignAudience::AGES as $i => $a) {
                $rows[] = ['age', $a, max(1, $ages[$i] + mt_rand(-4, 4))];
            }
            foreach ($cities as $city => $share) {
                $rows[] = ['city', $city, round($share * (mt_rand(80, 120) / 100), 1)];
            }
            foreach (['Polska' => 91.5, 'Niemcy' => 3.1, 'Wielka Brytania' => 2.2, 'Holandia' => 1.1, 'Irlandia' => 0.8] as $country => $share) {
                $rows[] = ['country', $country, $share];
            }
            foreach ($rows as [$dim, $label, $share]) {
                $c->audiences()->create(['dimension' => $dim, 'label' => $label, 'share' => $share]);
            }
        }
    }
}
