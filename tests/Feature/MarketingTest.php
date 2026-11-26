<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AudienceImport;
use App\Services\CampaignInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    private function campaignWithAd(): Campaign
    {
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Wiosna', 'budget' => 2000, 'status' => 'active',
            'starts_on' => today()->subDays(10), 'ends_on' => today()->addDays(10)]);
        $ad = AdCampaign::create(['client_id' => $acc->client_id, 'campaign_id' => $campaign->id, 'platform' => 'meta', 'name' => 'Ad', 'budget' => 1000]);
        $ad->results()->create(['measured_on' => today()->subDay(), 'spend' => 500, 'impressions' => 40000, 'reach' => 20000,
            'clicks' => 800, 'landing_views' => 600, 'add_to_cart' => 60, 'conversions' => 20, 'revenue' => 2500]);

        return $campaign;
    }

    public function test_insights_totals_and_funnel(): void
    {
        $campaign = $this->campaignWithAd();
        $ins = new CampaignInsights($campaign);
        $t = $ins->totals();
        $this->assertSame(20000, (int) $t['reach']);
        $this->assertSame(800, (int) $t['clicks']);
        $this->assertSame(5.0, (float) $t['roas']);

        $funnel = collect($ins->funnel($t))->keyBy('key');
        $this->assertSame(600, (int) $funnel['visits']->value);
        $this->assertSame(3.0, (float) $funnel['visits']->of_total);
        $this->assertSame(10.0, (float) $funnel['cart']->step_rate);
        $this->assertSame(0.1, (float) $funnel['result']->of_total);
    }

    public function test_index_renders_all_tabs_and_views(): void
    {
        $this->admin();
        $campaign = $this->campaignWithAd();
        foreach (['summary', 'settings', 'creatives', 'audience', 'results'] as $tab) {
            $this->get(route('social.campaigns.index', ['c' => $campaign->id, 'tab' => $tab]))->assertOk()->assertSee('Wiosna');
        }
        $this->get(route('social.campaigns.index', ['view' => 'grid', 'status' => 'active']))->assertOk()->assertSee('Wiosna');
        $this->get(route('social.campaigns.index', ['status' => 'finished']))->assertOk()->assertDontSee('Wiosna</strong>', false);
    }

    public function test_audience_manual_form_and_csv_import(): void
    {
        $this->admin();
        $campaign = $this->campaignWithAd();
        $this->put(route('social.campaigns.audience', $campaign), [
            'female' => 60, 'male' => 40, 'age' => ['25-34' => 30, '35-44' => 25],
            'cities' => "Warszawa: 20,5\nKraków: 10", 'countries' => 'Polska: 95',
        ])->assertRedirect();
        $aud = (new CampaignInsights($campaign->fresh()))->audience();
        $this->assertSame(60.0, (float) $aud['gender']['female']);
        $this->assertSame(20.5, (float) $aud['city']['Warszawa']);
        $this->assertSame(95.0, (float) $aud['country']['Polska']);

        $csv = "Wiek,Płeć,Zasięg\n18-24,kobiety,300\n18-24,mężczyźni,100\n25-34,kobiety,600\n";
        $this->assertNotEmpty(AudienceImport::parse($csv));
        $this->post(route('social.campaigns.audience.import', $campaign), [
            'csv' => UploadedFile::fake()->createWithContent('demo.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $aud = (new CampaignInsights($campaign->fresh()))->audience();
        $this->assertEqualsWithDelta(90.0, (float) $aud['gender']['female'], 0.1);
        $this->assertArrayHasKey('Warszawa', $aud['city']); // import wieku/płci nie kasuje miast
    }

    public function test_client_cannot_open_marketing(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)->withSession(['two_factor_passed' => true]);
        $this->get(route('social.campaigns.index'))->assertForbidden();
    }
}
