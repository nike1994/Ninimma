<?php

namespace Database\Seeders;

use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\TicketService;
use App\Services\Totp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Dane demonstracyjne do lokalnego testu (NIE uruchamiać na produkcji):
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(TicketService $tickets): void
    {
        Notification::fake();
        $secret = Totp::generateSecret();
        $admin = User::create([
            'name' => 'Wiktoria Admin', 'email' => 'admin@example.com', 'password' => 'demo-haslo-123', 'role' => 'admin',
        ]);
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $client = Client::create(['name' => 'Zielony Dom', 'company_name' => 'Zielony Dom sp. z o.o.', 'contact_email' => 'anna@example.com', 'contact_phone' => '+48 600 000 000']);
        $anna = User::create(['name' => 'Anna Kowalska', 'email' => 'anna@example.com', 'password' => 'demo-haslo-123', 'client_id' => $client->id]);
        $other = Client::create(['name' => 'Studio Ewa', 'company_name' => 'Ewa Zielińska Fotografia']);
        User::create(['name' => 'Ewa Zielińska', 'email' => 'ewa@example.com', 'password' => 'demo-haslo-123', 'client_id' => $other->id]);
        $t4 = $tickets->create($admin, ['client_id' => $other->id, 'title' => 'Nowe zdjęcia produktów', 'type' => 'task'], 'Prosimy o przesłanie zdjęć do galerii.');
        $tickets->changeStatus($t4, $admin, TicketStatus::WaitingClient);
        $t4->forceFill(['waiting_since' => now()->subDays(6), 'stalled_at' => now()->subDay(), 'reminders_sent' => 2])->save();
        $tickets->create($admin, ['client_id' => $client->id, 'title' => 'Optymalizacja strony głównej', 'type' => 'seo_content'], 'Metadane i szybkość ładowania.');

        $t1 = $tickets->create($anna, ['title' => 'Błąd przycisku „Dodaj do koszyka”', 'type' => 'bug'],
            'Przycisk „Dodaj do koszyka” nie działa na telefonie. Po kliknięciu nic się nie dzieje.');
        $tickets->postMessage($t1, $admin, 'Znalazłam przyczynę: to funkcja na serwerze, nie przycisk. Przygotowałam trzy opcje naprawy.', false);
        $tickets->postMessage($t1, $admin, 'Biblioteka płatności po aktualizacji wymaga nowszego PHP — sprawdzić hosting przed pełną naprawą.', true);
        $tickets->changeStatus($t1, $admin, TicketStatus::NeedsDecision);
        $tickets->postMessage($t1, $admin, 'Potrzebuję decyzji: pełna naprawa, obejście czy stop po diagnozie?', false);

        $t2 = $tickets->create($admin, ['client_id' => $client->id, 'title' => 'Artykuł na blog: grudzień', 'type' => 'article'],
            'Szkic do akceptacji w przyszłym tygodniu.');
        $tickets->create($admin, ['client_id' => $client->id, 'title' => 'Opieka nad stroną — listopad', 'type' => 'site_review'], null);
        $tickets->changeStatus($t2, $admin, TicketStatus::Blocked, 'Czekamy na zdjęcia od fotografa');

        $this->command?->info("Admin: admin@example.com / demo-haslo-123, sekret 2FA: {$secret}");
        $this->command?->info('Klient: anna@example.com / demo-haslo-123');
    }
}
