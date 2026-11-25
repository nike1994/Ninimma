<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Review;
use App\Models\SeoPhrase;
use App\Models\Site;
use App\Models\SiteDeadline;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SiteService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SitesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['two_factor_passed' => true]);

        return $admin;
    }

    public function test_site_reviews_package_and_log(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $client = Client::factory()->create();
        $this->post(route('sites.store'), ['client_id' => $client->id, 'domain' => 'example.pl', 'platform' => 'wordpress', 'care_hours' => 10,
            'review_recurrence' => 'monthly', 'next_review_on' => today()->toDateString()])->assertRedirect();
        $site = Site::first();

        // Cron: przegląd tworzy się sam jako zlecenie z listą kontrolną.
        $this->assertSame(1, app(SiteService::class)->runDueReviews());
        $this->assertSame(0, app(SiteService::class)->runDueReviews());
        $review = Review::first();
        $this->assertSame('site_review', $review->ticket->type->value);
        $this->assertSame($site->id, $review->ticket->site_id);
        $this->assertTrue($site->fresh()->next_review_on->isSameDay(today()->addMonthNoOverflow()));
        $this->assertCount(7, $review->items);

        $this->put(route('sites.reviews.update', $review), ['checked' => [0 => 1, 1 => 1], 'notes' => 'Kopia OK', 'complete' => 1])->assertRedirect();
        $this->assertNotNull($review->fresh()->completed_at);
        $this->assertSame(TicketStatus::Done, $review->ticket->fresh()->status);
        $this->assertStringContainsString('2/7', $site->logs()->first()->description);

        // Problem z przeglądu → osobne zlecenie powiązane ze stroną.
        $this->post(route('sites.reviews.problem', $review), ['title' => 'Formularz nie wysyła'])->assertRedirect();
        $problem = Ticket::where('title', 'Formularz nie wysyła')->first();
        $this->assertSame($site->id, $problem->site_id);
        $this->assertSame($review->ticket_id, $problem->parent_id);

        // Pakiet godzin z wpisów czasu zleceń strony; zakończenie zlecenia trafia do dziennika.
        app(TicketService::class)->logTime($problem, $admin, 150, today()->toDateString(), null);
        $this->assertSame(150, $site->minutesUsed(now()));
        app(TicketService::class)->changeStatus($problem, $admin, TicketStatus::Done);
        $this->assertTrue($site->logs()->where('kind', 'incident')->exists());

        // Termin: odnowienie tworzy kolejny za rok.
        $this->post(route('sites.deadlines.store', $site), ['kind' => 'domain', 'name' => 'example.pl', 'due_on' => today()->addDays(10)->toDateString()]);
        $d = SiteDeadline::first();
        $this->get(route('dashboard'))->assertSee('example.pl');
        $this->post(route('deadlines.renew', $d));
        $this->assertNotNull($d->fresh()->renewed_at);
        $this->assertTrue(SiteDeadline::whereNull('renewed_at')->first()->due_on->isSameDay(today()->addDays(10)->addYear()));
    }

    public function test_seo_positions_import_content_and_client_report(): void
    {
        Notification::fake();
        $this->admin();
        $client = Client::factory()->create();
        $site = Site::create(['client_id' => $client->id, 'domain' => 'sklep.pl', 'care_hours' => 5]);
        $this->post(route('sites.phrases.store', $site), ['phrase' => 'sklep z kwiatami', 'priority' => 1, 'target_url' => '/kwiaty']);
        $phrase = SeoPhrase::first();
        $this->post(route('sites.positions.store', $site), ['measured_on' => today()->subDays(20)->toDateString(), 'pos' => [$phrase->id => 18]]);
        $this->post(route('sites.positions.store', $site), ['measured_on' => today()->toDateString(), 'pos' => [$phrase->id => 7]]);
        $this->assertSame(2, $phrase->positions()->count()); // bez nadpisywania
        [$last, $change] = $phrase->fresh()->latestWithChange();
        $this->assertSame(7, $last->position);
        $this->assertSame(11, $change);

        $csv = UploadedFile::fake()->createWithContent('pozycje.csv', "fraza;data;pozycja\nsklep z kwiatami;".today()->toDateString().";5\nkwiaty warszawa;".today()->toDateString().";12\n");
        $this->post(route('sites.positions.import', $site), ['csv' => $csv])->assertRedirect();
        $this->assertSame(2, $site->phrases()->count());

        $this->post(route('sites.seo-contents.store', $site), ['working_title' => 'Jak dbać o storczyki', 'seo_phrase_id' => $phrase->id, 'due_on' => today()->addMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString()]);
        $this->assertSame('seo_content', Ticket::latest('id')->first()->type->value);
        $this->post(route('sites.backlinks.store', $site), ['url' => 'https://blog.example/link', 'cost' => 300]);
        $this->post(route('sites.seo-tasks.store', $site), ['description' => 'Przekierowania 301', 'priority' => 1]);

        foreach (['overview', 'reviews', 'seo', 'report'] as $tab) {
            $this->get(route('sites.show', [$site, 'tab' => $tab]))->assertOk();
        }
        $this->get(route('sites.index'))->assertOk();
        $this->get(route('calendar'))->assertOk()->assertSee('Jak dbać o storczyki');
        $this->get(route('checklists.index'))->assertOk();

        $user = User::factory()->create(['client_id' => $client->id]);
        $this->actingAs($user)->get(route('client.sites'))->assertOk()->assertSee('sklep z kwiatami')->assertSee('Jak dbać o storczyki')->assertDontSee('blog.example');
        $this->actingAs($user)->get(route('sites.show', $site))->assertForbidden();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('client.sites'))->assertOk()->assertDontSee('sklep.pl');
    }
}
