<?php

namespace Database\Seeders;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\TicketService;
use App\Services\Totp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Dane demonstracyjne do lokalnego testu (NIE uruchamiać na produkcji):
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(TicketService $t): void
    {
        Notification::fake();
        $now = Carbon::now();
        $at = fn (int $daysAgo, int $hour = 10) => Carbon::setTestNow($now->copy()->subDays($daysAgo)->setTime($hour, rand(0, 59)));

        $secret = Totp::generateSecret();
        $admin = User::create(['name' => 'Wiktoria Admin', 'email' => 'admin@example.com', 'password' => 'demo-haslo-123', 'role' => 'admin']);
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $team = collect([$admin]);
        foreach (['Kamil Wójcik', 'Ola Mazur', 'Piotr Lis'] as $i => $name) {
            $team->push(User::create(['name' => $name, 'email' => "team{$i}@example.com", 'password' => 'demo-haslo-123', 'role' => 'admin']));
        }

        $zielony = Client::create(['name' => 'Zielony Dom', 'company_name' => 'Zielony Dom sp. z o.o.', 'contact_email' => 'anna@example.com', 'contact_phone' => '+48 600 000 000']);
        $anna = User::create(['name' => 'Anna Kowalska', 'email' => 'anna@example.com', 'password' => 'demo-haslo-123', 'client_id' => $zielony->id]);
        $fit = Client::create(['name' => 'FitLife', 'company_name' => 'FitLife S.A.']);
        $marek = User::create(['name' => 'Marek Nowak', 'email' => 'marek@example.com', 'password' => 'demo-haslo-123', 'client_id' => $fit->id]);
        $studio = Client::create(['name' => 'Studio Blu', 'company_name' => 'Studio Blu Ewa Zielińska']);
        $ewa = User::create(['name' => 'Ewa Zielińska', 'email' => 'ewa@example.com', 'password' => 'demo-haslo-123', 'client_id' => $studio->id]);

        // [klient, autor, tytuł, typ, priorytet, termin(dni), prowadzący, dni temu]
        $plan = [
            [$zielony, $anna, 'Błąd przycisku „Dodaj do koszyka”', 'bug', 'urgent', 0, 0, 9],
            [$zielony, $admin, 'Finalna akceptacja kreacji kampanii', 'campaign', 'high', 2, 1, 8],
            [$fit, $marek, 'Audyt SEO – analiza i rekomendacje', 'seo_content', 'normal', 5, 2, 8],
            [$fit, $admin, 'Aktualizacja planu publikacji', 'task', 'normal', 7, 1, 7],
            [$studio, $ewa, 'Nowe zdjęcia produktów do galerii', 'task', 'normal', null, 3, 7],
            [$fit, $admin, 'Przygotować brief dla influencera', 'campaign', 'high', 1, 2, 5],
            [$studio, $admin, 'Uzupełnić treści na stronę www', 'article', 'normal', 10, 3, 4],
            [$zielony, $admin, 'Artykuł na blog: grudzień', 'article', 'normal', 12, 1, 4],
            [$fit, $marek, 'Wersja finalna grafik do publikacji', 'task', 'high', 3, 1, 3],
            [$studio, $admin, 'Przygotowanie oferty rozszerzonej', 'other', 'normal', null, 0, 2],
            [$zielony, $admin, 'Opieka nad stroną — listopad', 'site_review', 'normal', 6, 3, 2],
            [$fit, $admin, 'Zebrać materiały do kampanii', 'campaign', 'normal', 9, null, 1],
        ];
        $tickets = [];
        foreach ($plan as $i => [$client, $author, $title, $type, $prio, $due, $who, $ago]) {
            $at($ago, 9);
            $tickets[$i] = $t->create($author, [
                'client_id' => $client->id, 'title' => $title, 'type' => $type,
            ], $author->isAdmin() ? 'Zakres i ustalenia w opisie.' : 'Prośba od klienta — szczegóły w załączniku.');
            $tickets[$i]->forceFill(['priority' => $prio, 'due_on' => $due === null ? null : $now->copy()->addDays($due)->toDateString(),
                'assigned_to' => $who === null ? null : $team[$who]->id])->save();
        }

        // Rozmowy rozłożone na ostatnie dni (wykres aktywności).
        $clientOf = fn ($ticket) => [$zielony->id => $anna, $fit->id => $marek, $studio->id => $ewa][$ticket->client_id];
        foreach (range(9, 0) as $ago) {
            foreach (array_rand(array_slice($tickets, 0, 9, true), 3) as $k) {
                $ticket = $tickets[$k];
                $at($ago, rand(8, 12));
                $t->postMessage($ticket, $team[rand(0, 3)], 'Aktualizacja: przesyłam postęp prac i kolejne kroki.', false);
                if (rand(0, 1)) {
                    $at($ago, rand(13, 17));
                    $t->postMessage($ticket, $clientOf($ticket), 'Dziękuję, czy możemy wprowadzić drobne poprawki?', false);
                }
                $t->logTime($ticket, $team[rand(0, 3)], rand(2, 8) * 15, $now->copy()->subDays($ago)->toDateString(), 'Prace bieżące');
            }
        }

        $at(0, 10);
        $t->postMessage($tickets[0], $admin, 'Znalazłam przyczynę: to funkcja na serwerze, nie przycisk. Przygotowałam trzy opcje naprawy.', false);
        $t->postMessage($tickets[0], $admin, 'Biblioteka płatności po aktualizacji wymaga nowszego PHP — sprawdzić hosting przed pełną naprawą.', true);
        $t->changeStatus($tickets[0], $admin, TicketStatus::NeedsDecision);
        $at(0, 10);
        $t->postMessage($tickets[0], $anna, 'Czy możemy wprowadzić poprawki do wyceny?', false);
        $t->postMessage($tickets[2], $marek, 'Wyniki audytu są gotowe? Czekamy na podsumowanie.', false);
        $t->postMessage($tickets[4], $ewa, 'Potwierdzam publikację na czwartek. Materiały w załączniku.', false);

        $t->changeStatus($tickets[1], $admin, TicketStatus::NeedsDecision);
        $t->changeStatus($tickets[8], $admin, TicketStatus::WaitingClient);
        $t->changeStatus($tickets[7], $admin, TicketStatus::Blocked, 'Czekamy na zdjęcia od fotografa');
        $t->changeStatus($tickets[4], $admin, TicketStatus::WaitingClient);
        $tickets[4]->forceFill(['waiting_since' => $now->copy()->subDays(6), 'stalled_at' => $now->copy()->subDay(), 'reminders_sent' => 2])->save();

        foreach ([['Wycena i zakres prac', $studio, 3], ['Poprawki w treściach SEO', $fit, 2], ['Wysłanie materiałów do klienta', $zielony, 1]] as [$title, $client, $ago]) {
            $at($ago + 2, 9);
            $done = $t->create($admin, ['client_id' => $client->id, 'title' => $title, 'type' => 'task'], null);
            $t->logTime($done, $admin, 90, $now->copy()->subDays($ago + 1)->toDateString(), null);
            $at($ago, 16);
            $t->changeStatus($done, $admin, TicketStatus::Done);
        }
        Carbon::setTestNow();
        $this->call(SocialDemoSeeder::class);
        $this->call(InfluencerDemoSeeder::class);
        $this->call(SitesDemoSeeder::class);

        $this->command?->info("Admin: admin@example.com / demo-haslo-123, sekret 2FA: {$secret}");
        $this->command?->info('Klientka: anna@example.com / demo-haslo-123');
    }
}
