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
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Wiosna', 'budget' => 2000, 'status' => 'active', 'objective' => 'sales', 'target_value' => 5000,
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
        $this->assertSame(0.1, (float) $funnel['purchase']->of_total);

        // Cel: sprzedaż 2500 zł z 5000 zł → 50%; wskaźniki dobrane do sprzedaży.
        $goal = $ins->goal($t);
        $this->assertSame(['sales', 'revenue', 50], [$goal->objective, $goal->metric, $goal->pct]);
        $this->assertSame(['revenue', 'roas', 'purchases', 'cost_per_purchase'], array_column($ins->metrics($t), 'key'));
        $this->assertSame(25.0, collect($ins->metrics($t))->firstWhere('key', 'cost_per_purchase')->value);

        // Zasięg: inne wskaźniki i brak lejka.
        $campaign->update(['objective' => 'awareness', 'target_value' => 40000]);
        $ins = new CampaignInsights($campaign->fresh());
        $this->assertSame(['reach', 'frequency', 'cpm', 'engagement'], array_column($ins->metrics($t), 'key'));
        $this->assertSame(2.0, collect($ins->metrics($t))->firstWhere('key', 'frequency')->value);
        $this->assertSame(12.5, collect($ins->metrics($t))->firstWhere('key', 'cpm')->value);
        $this->assertSame(50, $ins->goal($t)->pct);
        $this->assertTrue($ins->funnel($t)->isEmpty());

        // Leady: lejek kończy się leadem.
        $campaign->update(['objective' => 'leads', 'leads' => 30]);
        $ins = new CampaignInsights($campaign->fresh());
        $t = $ins->totals();
        $this->assertSame(['reach', 'visits', 'lead'], $ins->funnel($t)->pluck('key')->all());
    }

    public function test_budget_pacing_and_weekly_alert(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        User::factory()->admin()->create();
        $campaign = $this->campaignWithAd(); // 11. dzień z 21, reklama 500 z 1000 zł → w normie
        $ins = new CampaignInsights($campaign);
        $this->assertSame('ok', $ins->pacing($ins->totals())->state);

        $campaign->ads()->first()->results()->first()->update(['spend' => 900]);
        $ins = new CampaignInsights($campaign->fresh());
        $p = $ins->pacing($ins->totals());
        $this->assertSame('over', $p->state);
        $this->assertSame(90, $p->spend_pct);

        $alerts = app(\App\Services\CampaignAlerts::class);
        $this->assertSame(1, $alerts->sendPacingAlerts());
        $this->assertSame(0, $alerts->sendPacingAlerts()); // ten sam alert najwyżej raz w tygodniu
        \Illuminate\Support\Facades\Notification::assertSentTimes(\App\Notifications\BudgetPacingAlert::class, 1);

        $campaign->ads()->first()->results()->first()->update(['spend' => 100]);
        $ins = new CampaignInsights($campaign->fresh());
        $this->assertSame('under', $ins->pacing($ins->totals())->state);
    }

    public function test_meta_ads_sync_fetches_results_and_audience(): void
    {
        \App\Services\Social\Integrations::saveMetaAds(true, 'system-token');
        $campaign = $this->campaignWithAd();
        $ad = $campaign->ads()->first();
        $ad->update(['external_id' => '120210000000001']);
        \Illuminate\Support\Facades\Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);
            $this->assertSame('system-token', $q['access_token']);
            $data = match ($q['breakdowns'] ?? null) {
                'age,gender' => [['age' => '25-34', 'gender' => 'female', 'reach' => '600'], ['age' => '25-34', 'gender' => 'male', 'reach' => '200'], ['age' => '35-44', 'gender' => 'female', 'reach' => '200']],
                'region' => [['region' => 'Masovian Voivodeship', 'reach' => '750'], ['region' => 'Lesser Poland Voivodeship', 'reach' => '250']],
                'country' => [['country' => 'PL', 'reach' => '900'], ['country' => 'DE', 'reach' => '100']],
                default => [['spend' => '812.40', 'impressions' => '61000', 'reach' => '30500', 'clicks' => '1900', 'inline_link_clicks' => '1400',
                    'actions' => [['action_type' => 'landing_page_view', 'value' => '1100'], ['action_type' => 'omni_add_to_cart', 'value' => '90'], ['action_type' => 'add_to_cart', 'value' => '90'], ['action_type' => 'omni_purchase', 'value' => '31']],
                    'action_values' => [['action_type' => 'omni_purchase', 'value' => '4030.50']]]],
            };

            return \Illuminate\Support\Facades\Http::response(['data' => $data]);
        });

        $this->assertSame(1, app(\App\Services\Ads\AdsSync::class)->run());
        $r = $ad->fresh()->latestResult;
        $this->assertSame('meta', $r->source);
        $this->assertSame([812.4, 30500, 1400, 1100, 90, 31, 4030.5], [(float) $r->spend, $r->reach, $r->clicks, $r->landing_views, $r->add_to_cart, $r->conversions, (float) $r->revenue]);
        $this->assertNotNull($ad->fresh()->synced_at);

        $aud = (new CampaignInsights($campaign->fresh()))->audience();
        $this->assertSame(80.0, $aud['gender']['female']);
        $this->assertSame(80.0, $aud['age']['25-34']);
        $this->assertSame(75.0, $aud['city']['Mazowieckie']);
        $this->assertSame(90.0, $aud['country']['Polska']);

        // Drugie pobranie tego samego dnia nadpisuje pomiar zamiast dodawać nowy.
        app(\App\Services\Ads\AdsSync::class)->run();
        $this->assertSame(1, $ad->results()->where('source', 'meta')->count());
    }

    public function test_meta_sync_button_and_error_message(): void
    {
        $this->admin();
        $campaign = $this->campaignWithAd();
        $this->post(route('social.campaigns.sync', $campaign))->assertSessionHas('error', __('mkt.ads_sync_off'));
        \App\Services\Social\Integrations::saveMetaAds(true, 'tok');
        $this->post(route('social.campaigns.sync', $campaign))->assertSessionHas('error', __('mkt.ads_no_ids'));
        $campaign->ads()->first()->update(['external_id' => '999']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['error' => ['message' => 'Unsupported get request', 'code' => 100]], 400)]);
        $this->post(route('social.campaigns.sync', $campaign))->assertSessionHas('error');
        $this->assertStringContainsString('Unsupported', $campaign->ads()->first()->sync_error);
    }

    public function test_campaign_form_saves_objective_and_target(): void
    {
        $this->admin();
        $campaign = $this->campaignWithAd();
        $this->put(route('social.campaigns.update', $campaign), ['client_id' => $campaign->client_id, 'name' => 'Wiosna', 'budget' => 2000,
            'status' => 'active', 'objective' => 'leads', 'target_value' => 120])->assertRedirect();
        $this->assertSame(['leads', 120.0], [$campaign->fresh()->objective, $campaign->fresh()->target_value]);
        $this->get(route('social.campaigns.index', ['c' => $campaign->id]))->assertOk()->assertSee(__('mkt.m_cpl'));
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

    public function test_client_sees_own_campaigns_without_internal_data(): void
    {
        $campaign = $this->campaignWithAd();
        $campaign->ads()->first()->results()->first()->update(['spend' => 900]); // tempo „za szybko” — tylko dla agencji
        $draft = Campaign::create(['client_id' => $campaign->client_id, 'name' => 'Szkic tajny', 'status' => 'draft']);
        $other = Campaign::create(['client_id' => SocialAccount::factory()->create()->client_id, 'name' => 'Cudza kampania', 'status' => 'active']);

        $client = User::factory()->create(['client_id' => $campaign->client_id]);
        $this->actingAs($client)->withSession(['two_factor_passed' => true]);
        $this->get(route('reports.index'))->assertOk()
            ->assertSee('Wiosna')->assertSee(__('mkt.goal_pct', ['n' => 50]))->assertSee(__('mkt.m_cost_per_purchase'))
            ->assertDontSee('Szkic tajny')->assertDontSee('Cudza kampania')
            ->assertDontSee(__('mkt.pace_over'))->assertDontSee(__('mkt.pace_short_over'));
        // Cudza kampania nie otwiera się przez ?c=, a parametr client jest ignorowany.
        $this->get(route('reports.index', ['c' => $other->id, 'client' => $other->client_id]))->assertOk()->assertDontSee('Cudza kampania');
        $this->get(route('reports.campaign', $other))->assertForbidden();
    }

    public function test_admin_previews_client_view(): void
    {
        $this->admin();
        $campaign = $this->campaignWithAd();
        $this->get(route('reports.index'))->assertRedirect(route('social.campaigns.index'));
        $this->get(route('reports.index', ['client' => $campaign->client_id, 'c' => $campaign->id]))->assertOk()
            ->assertSee(__('mkt.preview_as', ['client' => $campaign->client->name]))->assertSee('Wiosna');
        $this->get(route('social.campaigns.index', ['c' => $campaign->id]))->assertSee(__('mkt.preview_client'));
    }

    public function test_retro_saved_shown_to_next_campaign_and_optionally_to_client(): void
    {
        $admin = $this->admin();
        $old = $this->campaignWithAd();
        $old->update(['status' => 'finished']);
        $this->get(route('social.campaigns.index', ['c' => $old->id]))->assertSee(__('mkt.retro_prompt'));
        $this->put(route('social.campaigns.retro', $old), ['retro_worked' => 'Rolki działały świetnie', 'retro_failed' => 'Drogie kliknięcia', 'retro_next' => 'Więcej rolek'])
            ->assertRedirect();
        $old->refresh();
        $this->assertTrue($old->hasRetro());
        $this->assertFalse($old->retro_shared);
        $this->assertSame($admin->id, $old->retro_by);

        // Nowa kampania tego klienta pokazuje wnioski z poprzedniej.
        $next = Campaign::create(['client_id' => $old->client_id, 'name' => 'Lato', 'status' => 'review', 'starts_on' => today()->addDays(5), 'ends_on' => today()->addDays(30)]);
        $this->get(route('social.campaigns.index', ['c' => $next->id]))->assertSee(__('mkt.lessons'))->assertSee('Rolki działały świetnie');

        // Klient widzi wnioski dopiero po udostępnieniu.
        $client = User::factory()->create(['client_id' => $old->client_id]);
        $this->actingAs($client)->get(route('reports.index', ['c' => $old->id]))->assertOk()->assertDontSee('Rolki działały świetnie');
        $old->update(['retro_shared' => true]);
        $this->actingAs($client)->get(route('reports.index', ['c' => $old->id]))->assertSee('Rolki działały świetnie');
        $this->actingAs($client)->put(route('social.campaigns.retro', $old), ['retro_worked' => 'x'])->assertForbidden();
    }

    public function test_google_and_tiktok_ads_sync(): void
    {
        \App\Models\Setting::put('int_gads_enabled', '1');
        \App\Models\Setting::put('int_gads_client_id', 'cid.apps.googleusercontent.com');
        \App\Models\Setting::put('int_gads_login_customer', '111-222-3333');
        \App\Services\Social\Integrations::putEncrypted('int_gads_dev_token', 'DEV');
        \App\Services\Social\Integrations::putEncrypted('int_gads_client_secret', 'SECRET');
        \App\Services\Social\Integrations::putEncrypted('int_gads_refresh_token', 'REFRESH');
        \App\Models\Setting::put('int_ttads_enabled', '1');
        \App\Services\Social\Integrations::putEncrypted('int_ttads_token', 'TT-TOKEN');
        $this->assertSame(['google', 'tiktok'], \App\Services\Ads\AdsSync::platforms());

        $campaign = $this->campaignWithAd();
        $g = AdCampaign::create(['client_id' => $campaign->client_id, 'campaign_id' => $campaign->id, 'platform' => 'google', 'name' => 'Search',
            'external_id' => '123-456-7890:2233445566', 'status' => 'active', 'starts_on' => today()->subDays(5)]);
        $t = AdCampaign::create(['client_id' => $campaign->client_id, 'campaign_id' => $campaign->id, 'platform' => 'tiktok', 'name' => 'TT',
            'external_id' => '7012345678901234567:1789012345678901', 'status' => 'active']);
        $bad = AdCampaign::create(['client_id' => $campaign->client_id, 'campaign_id' => $campaign->id, 'platform' => 'google', 'name' => 'Zły ID',
            'external_id' => '42', 'status' => 'active']);

        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'ya29.x', 'expires_in' => 3599]),
            'googleads.googleapis.com/*' => function ($request) {
                $this->assertSame('DEV', $request->header('developer-token')[0]);
                $this->assertSame('1112223333', $request->header('login-customer-id')[0]);
                $this->assertStringContainsString('/customers/1234567890/googleAds:searchStream', $request->url());
                $this->assertStringContainsString('campaign.id = 2233445566', $request['query']);

                return \Illuminate\Support\Facades\Http::response([['results' => [
                    ['metrics' => ['costMicros' => '312450000', 'impressions' => '20000', 'clicks' => '900', 'conversions' => 18.0, 'conversionsValue' => 2340.5]],
                    ['metrics' => ['costMicros' => '100000000', 'impressions' => '5000', 'clicks' => '100', 'conversions' => 2.0, 'conversionsValue' => 260]],
                ]]]);
            },
            'business-api.tiktok.com/*' => function ($request) {
                $this->assertSame('TT-TOKEN', $request->header('Access-Token')[0]);
                $this->assertSame('7012345678901234567', $request['advertiser_id']);
                $this->assertStringContainsString('1789012345678901', $request['filtering']);

                return \Illuminate\Support\Facades\Http::response(['code' => 0, 'message' => 'OK', 'data' => ['list' => [['dimensions' => ['campaign_id' => '1789012345678901'],
                    'metrics' => ['spend' => '450.20', 'impressions' => '90000', 'reach' => '61000', 'clicks' => '1200', 'conversion' => '40', 'complete_payment' => '25', 'value_per_complete_payment' => '120.00']]]]]);
            },
        ]);

        $this->assertSame(2, app(\App\Services\Ads\AdsSync::class)->run());
        $gr = $g->fresh()->latestResult;
        $this->assertSame(['google', 412.45, 25000, 1000, 20, 2600.5], [$gr->source, (float) $gr->spend, $gr->impressions, $gr->clicks, $gr->conversions, (float) $gr->revenue]);
        $tr = $t->fresh()->latestResult;
        $this->assertSame(['tiktok', 450.2, 61000, 1200, 25, 3000.0], [$tr->source, (float) $tr->spend, $tr->reach, $tr->clicks, $tr->conversions, (float) $tr->revenue]);
        $this->assertStringContainsString('123-456-7890:2233445566', $bad->fresh()->sync_error);
    }
}
