<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\Influencer;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SocialAccount $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['two_factor_passed' => true]);
        $this->acc = SocialAccount::factory()->create();
    }

    private function pub(array $extra = []): Publication
    {
        $c = Content::create(['client_id' => $this->acc->client_id, 'title' => 'Post', 'description' => 'Opis', 'type' => 'post', 'format' => 'image', 'stage' => 'ready', 'created_by' => $this->admin->id]);

        return Publication::create($extra + ['content_id' => $c->id, 'social_account_id' => $this->acc->id, 'platform' => $this->acc->platform->value,
            'scheduled_at' => today()->addDays(3)->setTime(18, 30), 'status' => 'planned']);
    }

    public function test_dragging_moves_publication_shifts_promotion_and_resets_auto_publish_state(): void
    {
        $p = $this->pub(['promoted' => true, 'promo_budget' => 300, 'promo_from' => today()->addDays(3), 'promo_to' => today()->addDays(10)]);
        app(\App\Services\AdService::class)->syncFromPublication($p);
        $p->forceFill(['auto_publish' => true, 'api_status' => 'failed', 'api_attempts' => 3, 'api_error' => 'x'])->save();
        $at = today()->addDays(5)->format('Y-m-d').' 09:15';

        $this->patchJson(route('social.publications.reschedule', $p), ['at' => $at])->assertOk()->assertJson(['ok' => true, 'time' => '09:15']);
        $p->refresh();
        $this->assertSame($at, $p->scheduled_at->format('Y-m-d H:i'));
        $this->assertTrue($p->promo_from->isSameDay(today()->addDays(5)));
        $this->assertTrue($p->promo_to->isSameDay(today()->addDays(12)));
        $this->assertNull($p->api_status);
        $this->assertSame(0, $p->api_attempts);
        $this->assertTrue(AdCampaign::where('publication_id', $p->id)->first()->starts_on->isSameDay(today()->addDays(5)));
    }

    public function test_dragging_respects_license_and_only_planned_posts_move(): void
    {
        $p = $this->pub();
        $collab = Collaboration::create(['influencer_id' => Influencer::create(['name' => 'Ola'])->id,
            'campaign_id' => Campaign::create(['client_id' => $this->acc->client_id, 'name' => 'K', 'status' => 'active'])->id, 'rate' => 1]);
        $p->content->materials()->attach($collab->materials()->create(['description' => 'Rolka', 'license_until' => today()->addDays(4)])->id);

        $this->patchJson(route('social.publications.reschedule', $p), ['at' => today()->addDays(8)->format('Y-m-d').' 10:00'])
            ->assertStatus(422)->assertJsonFragment(['message' => __('lic.block_publish_expired', ['material' => 'Rolka', 'until' => today()->addDays(4)->format('d.m.Y'), 'date' => today()->addDays(8)->format('d.m.Y')])]);
        $this->assertTrue($p->fresh()->scheduled_at->isSameDay(today()->addDays(3)));
        $this->patchJson(route('social.publications.reschedule', $p), ['at' => today()->addDays(4)->format('Y-m-d').' 10:00'])->assertOk();

        $done = $this->pub(['status' => 'published', 'published_at' => now()]);
        $this->patchJson(route('social.publications.reschedule', $done), ['at' => today()->addDays(2)->format('Y-m-d').' 10:00'])->assertStatus(422);
        $this->patchJson(route('social.publications.reschedule', $p), ['at' => 'jutro'])->assertStatus(422);

        $client = User::factory()->create(['client_id' => $this->acc->client_id]);
        $this->actingAs($client)->patchJson(route('social.publications.reschedule', $p), ['at' => today()->addDays(2)->format('Y-m-d').' 10:00'])->assertForbidden();
    }

    public function test_calendar_shows_peek_drag_and_add_and_create_is_prefilled_from_day(): void
    {
        $p = $this->pub();
        $this->get(route('social.calendar', ['month' => $p->scheduled_at->format('Y-m')]))->assertOk()
            ->assertSee('data-peek', false)->assertSee('draggable="true"', false)
            ->assertSee(route('social.contents.create', ['date' => today()->addDays(3)->toDateString()]), false)
            ->assertSee('calendar-plan.js', false);
        $this->get(route('social.dashboard'))->assertOk()->assertSee('data-create', false);

        $date = today()->addDays(6)->toDateString();
        $this->get(route('social.contents.create', ['date' => $date, 'time' => '17:45', 'account' => $this->acc->id]))->assertOk()
            ->assertSee('value="'.$date.'T17:45"', false)
            ->assertSee('value="'.$this->acc->id.'" data-client="'.$this->acc->client_id.'" data-api="0" selected', false);

        // Klient widzi podgląd swoich publikacji, ale nie może przenosić ani dodawać.
        $p->update(['visible_to_client' => true]);
        $client = User::factory()->create(['client_id' => $this->acc->client_id]);
        $this->actingAs($client)->get(route('publications.calendar', ['month' => $p->scheduled_at->format('Y-m')]))->assertOk()
            ->assertSee('data-peek', false)->assertDontSee('draggable="true"', false)->assertDontSee('month-add', false);
    }
}
