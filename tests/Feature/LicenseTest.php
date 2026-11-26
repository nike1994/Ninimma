<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\Influencer;
use App\Models\Material;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\LicenseAlert;
use App\Services\CampaignReport;
use App\Services\LicenseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private SocialAccount $acc;

    private Campaign $campaign;

    private Collaboration $collab;

    private function setUpWorld(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);
        $this->acc = SocialAccount::factory()->create();
        $this->campaign = Campaign::create(['client_id' => $this->acc->client_id, 'name' => 'Wiosna', 'status' => 'active']);
        $this->collab = Collaboration::create(['influencer_id' => Influencer::create(['name' => 'Ola'])->id, 'campaign_id' => $this->campaign->id, 'rate' => 1000,
            'license_from' => today()->subDays(10), 'license_to' => today()->addDays(5), 'license_channels' => ['organic'], 'requires_takedown' => true]);

        return $admin;
    }

    public function test_new_material_inherits_contract_terms_and_status_counts_down(): void
    {
        $this->setUpWorld();
        $this->post(route('collaborations.materials.store', $this->collab), ['description' => 'Rolka'])->assertRedirect();
        $m = Material::first();
        $this->assertSame(['organic'], $m->license_channels);
        $this->assertTrue($m->requires_takedown);
        $this->assertTrue($m->license_from->isSameDay(today()->subDays(10)));
        $this->assertFalse($m->allows('paid'));

        $st = LicenseGuard::status($m);
        $this->assertSame('expiring', $st->state);
        $this->assertSame(5, $st->days_left);
        $this->assertSame(33, $st->pct_left); // 5 z 15 dni

        $m->update(['license_until' => today()->subDay()]);
        $this->assertSame('expired', LicenseGuard::status($m)->state);
        $m->update(['license_until' => null]);
        $this->assertSame('none', LicenseGuard::status($m)->state);
    }

    public function test_publishing_and_promoting_outside_license_is_blocked(): void
    {
        $this->setUpWorld();
        $m = $this->collab->materials()->create(['description' => 'Rolka', 'is_received' => true, 'license_from' => $this->collab->license_from,
            'license_until' => $this->collab->license_to, 'license_channels' => ['organic']]);
        $base = ['client_id' => $this->acc->client_id, 'title' => 'Repost', 'type' => 'reel', 'format' => 'video', 'stage' => 'ready', 'material_ids' => [$m->id]];
        $at = fn ($d) => today()->addDays($d)->setTime(12, 0)->format('Y-m-d H:i');

        // Post po końcu licencji (licencja do +5 dni) — zablokowany, nic się nie zapisuje.
        $this->post(route('social.contents.store'), $base + ['pubs' => [['social_account_id' => $this->acc->id, 'scheduled_at' => $at(9)]]])
            ->assertSessionHasErrors('pubs.0.scheduled_at');
        $this->assertSame(0, Content::count());

        // W okresie licencji — OK; promowanie posta przy licencji tylko na posty — zablokowane.
        $this->post(route('social.contents.store'), $base + ['pubs' => [['social_account_id' => $this->acc->id, 'scheduled_at' => $at(2),
            'promoted' => 1, 'promo_budget' => 200, 'promo_to' => today()->addDays(4)->toDateString()]]])->assertSessionHasErrors('pubs.0.promoted');
        $this->post(route('social.contents.store'), $base + ['pubs' => [['social_account_id' => $this->acc->id, 'scheduled_at' => $at(2)]]])->assertSessionHasNoErrors();
        $content = Content::first();
        $pub = Publication::first();
        $this->assertSame([$m->id], $content->materials->pluck('id')->all());

        // Zgoda na reklamy: promocja dłuższa niż licencja albo bez daty końca — zablokowana; w licencji — OK.
        $m->update(['license_channels' => ['organic', 'paid']]);
        $row = ['id' => $pub->id, 'social_account_id' => $this->acc->id, 'scheduled_at' => $at(2), 'promoted' => 1, 'promo_budget' => 200];
        $this->put(route('social.contents.update', $content), $base + ['pubs' => [$row + ['promo_to' => today()->addDays(20)->toDateString()]]])->assertSessionHasErrors('pubs.0.promoted');
        $this->put(route('social.contents.update', $content), $base + ['pubs' => [$row]])->assertSessionHasErrors('pubs.0.promoted');
        $this->assertFalse($pub->fresh()->promoted);
        $this->put(route('social.contents.update', $content), $base + ['pubs' => [$row + ['promo_to' => today()->addDays(5)->toDateString()]]])->assertSessionHasNoErrors();
        $this->assertTrue($pub->fresh()->promoted);

        // Reklama z materiałem: dłuższa niż licencja — zablokowana; w licencji — OK.
        $ad = ['client_id' => $this->acc->client_id, 'platform' => 'meta', 'name' => 'Dark post', 'objective' => 'reach', 'budget' => 300, 'status' => 'planned',
            'starts_on' => today()->toDateString(), 'material_id' => $m->id];
        $this->post(route('social.ads.store'), $ad + ['ends_on' => today()->addDays(20)->toDateString()])->assertSessionHasErrors('material_id');
        $this->post(route('social.ads.store'), $ad + ['ends_on' => today()->addDays(5)->toDateString()])->assertSessionHasNoErrors();
        $created = AdCampaign::where('material_id', $m->id)->first();

        // Licencja wygasła: nowego posta ani reklamy nie da się dodać, wznowienie reklamy zablokowane, cron jej nie uruchomi.
        $m->update(['license_from' => today()->subDays(30), 'license_until' => today()->subDay()]);
        $this->post(route('social.ads.store'), $ad + ['ends_on' => today()->addDays(3)->toDateString()])->assertSessionHasErrors('material_id');
        $created->update(['status' => 'paused']);
        $this->put(route('social.ads.update', $created), ['status' => 'active', 'ends_on' => today()->addDays(3)->toDateString()] + $ad)->assertSessionHasErrors('material_id');
        $this->put(route('social.contents.update', $content), $base + ['pubs' => [['social_account_id' => $this->acc->id, 'scheduled_at' => $at(1)]]])
            ->assertSessionHasErrors('pubs.0.scheduled_at');
        $created->update(['status' => 'planned']);
        app(\App\Services\AdService::class)->updateStatuses();
        $this->assertSame('planned', $created->fresh()->status);

        // Istniejące rzeczy zostają na liście konfliktów i w kalendarzu.
        $guard = app(LicenseGuard::class);
        $types = $guard->issuesFor($m)->pluck('type')->unique()->sort()->values()->all();
        $this->assertContains('publish_after_expiry', $types);
        $this->assertTrue($guard->issuesByPublication()->has($pub->id));
        $this->get(route('social.contents.edit', $content))->assertOk()->assertSee(__('lic.type_publish_after_expiry'));
        $this->get(route('social.calendar', ['month' => $pub->scheduled_at->format('Y-m')]))->assertOk()->assertSee('lic-flagged');
        $this->get(route('licenses.index', ['filter' => 'conflicts']))->assertOk()->assertSee('Rolka');

        // W wyborze materiałów wygasły jest zablokowany dla nowych treści.
        $this->get(route('social.contents.create'))->assertOk()->assertSee(__('lic.picker_expired'));
    }

    public function test_extension_updates_license_records_history_and_counts_in_campaign_costs(): void
    {
        $this->setUpWorld();
        $m = $this->collab->materials()->create(['description' => 'Rolka', 'license_until' => today()->addDays(5)]);
        $this->post(route('licenses.extend', $m), ['new_until' => today()->addMonths(3)->toDateString(), 'cost' => 350, 'note' => 'Aneks'])->assertRedirect();
        $m->refresh();
        $this->assertTrue($m->license_until->isSameDay(today()->addMonths(3)));
        $this->assertSame(1, $m->extensions()->count());
        $this->assertTrue($m->extensions->first()->previous_until->isSameDay(today()->addDays(5)));
        $this->assertSame(350.0, (new CampaignReport($this->campaign->fresh()))->costs()['licenses']);

        // Kanały: wszystkie zaznaczone = bez ograniczeń.
        $this->put(route('licenses.update', $m), ['license_until' => today()->addMonths(3)->toDateString(), 'license_channels' => \App\Models\Material::CHANNELS])->assertRedirect();
        $this->assertNull($m->fresh()->license_channels);
        $this->put(route('licenses.update', $m), ['license_until' => today()->addMonths(3)->toDateString(), 'license_channels' => ['organic', 'paid']])->assertRedirect();
        $this->assertSame(['organic', 'paid'], $m->fresh()->license_channels);
    }

    public function test_published_post_after_expiry_requires_takedown_and_alerts_go_once_per_threshold(): void
    {
        Notification::fake();
        $admin = $this->setUpWorld();
        $content = Content::create(['client_id' => $this->acc->client_id, 'title' => 'Post', 'type' => 'post', 'format' => 'video', 'stage' => 'ready', 'created_by' => $admin->id]);
        $pub = Publication::create(['content_id' => $content->id, 'social_account_id' => $this->acc->id, 'platform' => $this->acc->platform->value,
            'scheduled_at' => now()->subDays(5), 'published_at' => now()->subDays(5), 'status' => 'published']);
        $expired = $this->collab->materials()->create(['description' => 'Stara rolka', 'license_until' => today()->subDay(), 'requires_takedown' => true, 'publication_id' => $pub->id]);
        $this->assertSame(['takedown'], app(LicenseGuard::class)->issuesFor($expired)->pluck('type')->all());

        $this->collab->materials()->create(['description' => 'Rolka', 'license_until' => today()->addDays(6)]);
        $guard = app(LicenseGuard::class);
        $this->assertSame(1, $guard->sendAlerts());
        $this->assertSame(0, $guard->sendAlerts()); // ten sam próg (7 dni) nie wysyła się drugi raz
        Notification::assertSentTo($admin, LicenseAlert::class, fn ($n) => $n->items->count() === 1 && $n->conflicts === 1);
    }

    public function test_client_sees_own_material_countdown_but_not_license_screen(): void
    {
        $this->setUpWorld();
        $this->collab->materials()->create(['description' => 'Rolka klienta', 'is_received' => true, 'license_until' => today()->addDays(20)]);
        $client = User::factory()->create(['client_id' => $this->acc->client_id]);
        $this->actingAs($client);
        $this->get(route('materials.index'))->assertOk()->assertSee('Rolka klienta')->assertSee(__('lic.client_until'));
        $this->get(route('licenses.index'))->assertForbidden();
    }
}
