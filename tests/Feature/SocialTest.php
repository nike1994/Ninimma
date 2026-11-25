<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_content_with_publications_on_two_platforms(): void
    {
        $this->admin();
        $ig = SocialAccount::factory()->create();
        $fb = SocialAccount::factory()->create(['client_id' => $ig->client_id, 'platform' => 'facebook']);

        $this->post(route('social.contents.store'), [
            'client_id' => $ig->client_id, 'title' => 'Świąteczny post', 'type' => 'post', 'format' => 'image', 'stage' => 'preparing',
            'pubs' => [
                ['social_account_id' => $ig->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'status' => 'planned'],
                ['social_account_id' => $fb->id, 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'), 'status' => 'planned', 'caption' => 'Wersja FB'],
                ['social_account_id' => '', 'scheduled_at' => ''],
            ],
        ])->assertRedirect();

        $content = Content::first();
        $this->assertSame(2, $content->publications()->count());
        $this->assertSame('ready', $content->stage);
        $this->assertSame('Wersja FB', $content->publications()->where('platform', 'facebook')->value('caption'));
    }

    public function test_publication_must_use_account_of_same_client(): void
    {
        $this->admin();
        $mine = SocialAccount::factory()->create();
        $foreign = SocialAccount::factory()->create();
        $this->post(route('social.contents.store'), [
            'client_id' => $mine->client_id, 'title' => 'X', 'type' => 'post', 'format' => 'image', 'stage' => 'idea',
            'pubs' => [['social_account_id' => $foreign->id, 'scheduled_at' => now()->format('Y-m-d H:i')]],
        ])->assertSessionHasErrors();
    }

    public function test_stats_queue_and_measurements_are_never_overwritten(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Post']);
        $pub = Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => 'instagram',
            'scheduled_at' => now()->subDays(2), 'published_at' => now()->subDays(2), 'status' => 'published']);

        $this->assertCount(1, SocialStats::statsQueue());
        $this->post(route('social.stats'), ['m' => [$pub->id => ['reach' => 1000, 'reactions' => 50, 'comments' => 5, 'shares' => 2, 'clicks' => 30]]])->assertRedirect();
        $this->assertCount(0, SocialStats::statsQueue()); // następny pomiar po 7 dniach

        $this->travel(6)->days();
        $this->assertCount(1, SocialStats::statsQueue());
        $this->post(route('social.stats'), ['m' => [$pub->id => ['reach' => 2500, 'reactions' => 90]]]);
        $this->assertSame(2, $pub->measurements()->count());
        $this->assertSame(2500, $pub->fresh()->latestMeasurement->reach);

        $stats = new SocialStats('30d');
        $kpis = $stats->kpis();
        $this->assertSame(1, $kpis[0]['value']);
        $this->assertSame(2500, $kpis[1]['value']);
        $this->assertSame(90, $kpis[2]['value']);
    }

    public function test_client_sees_only_shared_publications_and_can_approve(): void
    {
        $acc = SocialAccount::factory()->create();
        $client = User::factory()->create(['client_id' => $acc->client_id]);
        $other = User::factory()->create();
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Widoczny post', 'description' => 'Opis']);
        $shared = Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => 'instagram', 'scheduled_at' => now()->addDay(), 'visible_to_client' => true]);
        $hidden = Content::create(['client_id' => $acc->client_id, 'title' => 'Roboczy szkic']);
        Publication::create(['content_id' => $hidden->id, 'social_account_id' => $acc->id, 'platform' => 'instagram', 'scheduled_at' => now()->addDay()]);

        $this->actingAs($client)->get(route('publications.index'))->assertOk()->assertSee('Widoczny post')->assertDontSee('Roboczy szkic');
        $this->actingAs($other)->get(route('publications.index'))->assertOk()->assertDontSee('Widoczny post');
        $this->actingAs($other)->post(route('publications.respond', $shared), ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($client)->post(route('publications.respond', $shared), ['decision' => 'changes', 'comment' => 'Inne zdjęcie'])->assertRedirect();
        $this->assertSame('changes', $shared->fresh()->client_status);
        $this->actingAs($client)->get(route('social.dashboard'))->assertForbidden();
    }

    public function test_social_pages_render(): void
    {
        $this->admin();
        $acc = SocialAccount::factory()->create();
        $content = Content::create(['client_id' => $acc->client_id, 'title' => 'Post']);
        Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => 'instagram',
            'scheduled_at' => now(), 'published_at' => now(), 'status' => 'published']);

        foreach (['social.dashboard', 'social.calendar', 'social.contents.index', 'social.contents.create', 'social.stats', 'social.accounts'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('social.dashboard', ['client' => $acc->client_id, 'period' => '7d']))->assertOk()->assertSee($acc->client->name);
        $this->get(route('social.contents.edit', $content))->assertOk();
    }
}
