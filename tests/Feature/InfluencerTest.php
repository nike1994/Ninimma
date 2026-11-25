<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\Influencer;
use App\Models\Material;
use App\Models\Publication;
use App\Models\Reminder;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\CampaignReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfluencerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_collaboration_lifecycle_report_and_visibility(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Black Friday', 'budget' => 5000, 'status' => 'active']);

        $this->post(route('influencers.store'), ['name' => 'Ola Influencerka', 'tags' => 'Uroda, eko, uroda',
            'profiles' => [['platform' => 'instagram', 'handle' => '@ola', 'followers' => 52000]], 'rating' => 5])->assertRedirect();
        $inf = Influencer::first();
        $this->assertSame(['uroda', 'eko'], $inf->tags);
        $this->assertSame(52000, $inf->followers());

        $this->post(route('collaborations.store'), ['influencer_id' => $inf->id, 'campaign_id' => $campaign->id, 'rate' => 1500,
            'billing' => 'invoice', 'license_to' => today()->addDays(10)->toDateString(), 'needs_shipment' => 1, 'private_notes' => 'Tajne'])->assertRedirect();
        $c = Collaboration::first();
        $this->assertSame('contract', $c->stage());

        foreach (['contract_sent', 'contract_signed', 'brief_sent', 'brief_accepted'] as $step) {
            $this->post(route('collaborations.step', $c), ['step' => $step]);
        }
        $this->assertSame('shipment', $c->fresh()->stage());
        $this->post(route('collaborations.shipments.store', $c), ['contents' => 'Zestaw kosmetyków', 'quantity' => 2, 'value' => 300]);
        $ship = $c->shipments()->first();
        $this->patch(route('shipments.update', $ship), ['field' => 'sent_on']);
        $this->assertSame('shipment', $c->fresh()->stage());
        $this->get(route('influencers.index', ['view' => 'table', 'filter' => 'not_delivered']))->assertSee('Ola Influencerka');
        $this->patch(route('shipments.update', $ship), ['field' => 'delivered_on']);
        $this->assertSame('materials', $c->fresh()->stage());

        // Materiał: otrzymany + powiązany z publikacją → komplet, wyniki przypisane do influencera.
        $this->post(route('collaborations.materials.store', $c), ['description' => 'Rolka 30 s']);
        $m = Material::first();
        $this->assertSame(today()->addDays(10)->toDateString(), $m->license_until->toDateString());
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Rolka Oli', 'format' => 'video']);
        $pub = Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => 'instagram',
            'scheduled_at' => now(), 'published_at' => now(), 'status' => 'published']);
        $pub->measurements()->create(['measured_on' => today(), 'reach' => 20000, 'reactions' => 900, 'comments' => 80, 'shares' => 20, 'clicks' => 300]);
        $this->put(route('materials.update', $m), ['is_received' => 1, 'publication_id' => $pub->id, 'license_until' => $m->license_until->toDateString()]);
        $this->assertSame('publication', $c->fresh()->stage());
        $this->post(route('collaborations.step', $c), ['step' => 'published']);
        $this->post(route('collaborations.step', $c), ['step' => 'paid']);
        $c->refresh();
        $this->assertSame('done', $c->status);
        $this->assertNull($c->private_notes); // notatki prywatne usunięte po zakończeniu

        $report = new CampaignReport($campaign->fresh());
        $costs = $report->costs();
        $this->assertSame(1800.0, $costs['rates'] + $costs['products']);
        $this->assertSame(20000, $report->results()['reach']);
        $this->assertSame(1000, $report->results()['engagement']);
        $this->assertSame(1800.0, $report->summary()['per_material']);
        $this->assertSame(1.8, $report->byInfluencer()->first()->cpe);

        // Klient: raport bez stawek i danych osobowych; gotowe materiały; brak dostępu do influencerów.
        $client = User::factory()->create(['client_id' => $acc->client_id]);
        $this->actingAs($client)->get(route('reports.campaign', $campaign))->assertOk()->assertDontSee('Ola Influencerka')->assertDontSee('1 500,00');
        $this->actingAs($client)->get(route('materials.index'))->assertOk()->assertSee('Rolka 30 s')->assertDontSee('Ola Influencerka');
        $this->actingAs($client)->get(route('influencers.index'))->assertForbidden();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('reports.campaign', $campaign))->assertForbidden();
        $this->actingAs($other)->get(route('materials.index'))->assertDontSee('Rolka 30 s');
    }

    public function test_cancel_reminders_and_pages(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $campaign = Campaign::create(['client_id' => $acc->client_id, 'name' => 'Lato']);
        $inf = Influencer::create(['name' => 'Kuba']);
        $c = Collaboration::create(['influencer_id' => $inf->id, 'campaign_id' => $campaign->id, 'rate' => 500]);
        $c->forceFill(['influencer_id' => $inf->id, 'campaign_id' => $campaign->id])->save();

        $this->post(route('reminders.store'), ['type' => 'influencer', 'id' => $inf->id, 'due_on' => today()->toDateString(), 'body' => 'Zapytać o termin']);
        $this->assertSame(1, Reminder::count());
        $this->get(route('dashboard'))->assertOk()->assertSee('Zapytać o termin');
        $this->patch(route('reminders.toggle', Reminder::first()));
        $this->assertTrue(Reminder::first()->is_done);

        $this->post(route('collaborations.cancel', $c), ['cancel_reason' => 'Brak terminu'])->assertRedirect();
        $this->assertSame('cancelled', $c->fresh()->stage());

        foreach (['grid', 'board', 'table'] as $v) {
            $this->get(route('influencers.index', ['view' => $v]))->assertOk();
        }
        $this->get(route('influencers.show', $inf))->assertOk();
        $this->get(route('influencers.edit', $inf))->assertOk();
        $this->get(route('collaborations.show', $c))->assertOk()->assertSee('Brak terminu');
        $this->get(route('materials.index'))->assertOk();
        $this->get(route('reports.campaign', $campaign))->assertOk();
        $this->get(route('social.campaigns.edit', $campaign))->assertOk();
    }
}
