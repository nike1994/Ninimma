<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Content;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UtmLink;
use App\Services\AdService;
use App\Services\CampaignReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_boosted_post_creates_ad_with_period_and_report_uses_actual_spend(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Black Friday', 'budget' => 3000, 'status' => 'active']);

        $this->post(route('social.contents.store'), [
            'client_id' => $acc->client_id, 'campaign_id' => $campaign->id, 'title' => 'Promocja BF', 'type' => 'post', 'format' => 'image', 'stage' => 'ready',
            'pubs' => [['social_account_id' => $acc->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'status' => 'published',
                'promoted' => 1, 'promo_budget' => 1000, 'promo_from' => today()->toDateString(), 'promo_to' => today()->addDays(7)->toDateString()]],
        ])->assertRedirect();

        $ad = AdCampaign::first();
        $this->assertNotNull($ad);
        $this->assertSame('meta', $ad->platform);
        $this->assertSame($campaign->id, $ad->campaign_id);
        $this->assertSame(1000.0, (float) $ad->budget);
        $this->assertTrue($ad->ends_on->isSameDay(today()->addDays(7)));

        // Bez pomiarów raport liczy budżet planowany, z pomiarem — rzeczywisty wydatek.
        $this->assertSame(1000.0, (new CampaignReport($campaign->fresh()))->costs()['promotion']);
        $this->post(route('social.ads.results.store', $ad), ['measured_on' => today()->toDateString(), 'spend' => 640, 'impressions' => 80000,
            'reach' => 50000, 'clicks' => 1600, 'conversions' => 32, 'revenue' => 5120])->assertRedirect();
        $report = new CampaignReport($campaign->fresh());
        $this->assertSame(640.0, $report->costs()['promotion']);
        $paid = $report->paid();
        $this->assertSame(0.4, $paid['cpc']);
        $this->assertSame(8.0, $paid['cpm']);
        $this->assertSame(2.0, $paid['ctr']);
        $this->assertSame(20.0, $paid['cpa']);
        $this->assertSame(8.0, $paid['roas']);
        $this->assertSame(21, $campaign->fresh()->budgetUsedPct()); // 640 / 3000

        // Pomiary nie nadpisują się; liczy się ostatni (narastający).
        $this->post(route('social.ads.results.store', $ad), ['measured_on' => today()->addDay()->toDateString(), 'spend' => 900, 'clicks' => 2000]);
        $this->assertSame(2, $ad->results()->count());
        $this->assertSame(900.0, $ad->fresh()->totals()['spend']);

        // Odznaczenie promocji nie kasuje reklamy z wynikami (historia wydatków).
        $content = Content::first();
        $pub = $content->publications()->first();
        $this->put(route('social.contents.update', $content), ['client_id' => $acc->client_id, 'title' => 'Promocja BF', 'type' => 'post', 'format' => 'image', 'stage' => 'ready',
            'pubs' => [['id' => $pub->id, 'social_account_id' => $acc->id, 'scheduled_at' => now()->format('Y-m-d H:i'), 'status' => 'published']]]);
        $this->assertSame(1, AdCampaign::count());

        $this->get(route('social.ads.index'))->assertOk()->assertSee('Promocja BF');
        $this->get(route('social.ads.edit', $ad))->assertOk();
        $this->get(route('reports.campaign', $campaign))->assertOk()->assertSee('ROAS');
        $this->get(route('social.dashboard'))->assertOk();
    }

    public function test_standalone_ad_csv_import_and_statuses(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $this->post(route('social.ads.store'), ['client_id' => $acc->client_id, 'platform' => 'google', 'name' => 'Search — buty', 'external_name' => 'GS_buty_2026',
            'objective' => 'conversions', 'budget' => 2000, 'status' => 'planned', 'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDay()->toDateString()])->assertRedirect();
        $ad = AdCampaign::first();

        $csv = "\xEF\xBB\xBFNazwa kampanii;Data zakończenia raportowania;Wydana kwota (PLN);Wyświetlenia;Zasięg;Kliknięcia linku;Wyniki\n"
            ."GS_buty_2026;".today()->toDateString().";1 234,56;45000;30000;900;27\nInna kampania;".today()->toDateString().";10;1;1;1;0\n";
        $this->post(route('social.ads.import'), ['csv' => UploadedFile::fake()->createWithContent('meta.csv', $csv)])
            ->assertRedirect()->assertSessionHas('unmatched', ['Inna kampania']);
        $t = $ad->fresh()->totals();
        $this->assertSame(1234.56, $t['spend']);
        $this->assertSame(900, $t['clicks']);
        $this->assertSame(27, $t['conversions']);

        // Cron: start i koniec według dat.
        $this->assertSame([1, 0], app(AdService::class)->updateStatuses());
        $this->assertSame('active', $ad->fresh()->status);
        $this->get(route('dashboard'))->assertSee('Search — buty'); // kończy się jutro
        $this->travel(3)->days();
        app(AdService::class)->updateStatuses();
        $this->assertSame('finished', $ad->fresh()->status);

        // Klient nie widzi listy reklam.
        $client = User::factory()->create(['client_id' => $acc->client_id]);
        $this->actingAs($client)->get(route('social.ads.index'))->assertForbidden();
    }

    public function test_utm_builder(): void
    {
        $this->admin();
        $this->assertSame('https://sklep.pl/p?x=1&utm_source=facebook&utm_medium=paid_social&utm_campaign=black_friday#top',
            UtmLink::build('https://sklep.pl/p?x=1#top', ['source' => 'Facebook', 'medium' => 'paid_social', 'name' => 'Black Friday']));
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'BF']);
        $this->post(route('social.utm.store', $campaign), ['base_url' => 'https://sklep.pl', 'source' => 'instagram', 'medium' => 'influencer', 'name' => 'bf', 'content' => 'ola'])->assertRedirect();
        $this->assertSame('https://sklep.pl?utm_source=instagram&utm_medium=influencer&utm_campaign=bf&utm_content=ola', UtmLink::first()->full_url);
        $this->get(route('social.campaigns.edit', $campaign))->assertOk()->assertSee('utm_content=ola', false);
    }
}
