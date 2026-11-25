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
            'name' => 'Administrator', 'email' => 'admin@example.com', 'password' => 'demo-haslo-123', 'role' => 'admin',
        ]);
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $client = Client::create(['name' => 'Kwiaciarnia Róża', 'company_name' => 'Róża sp. z o.o.', 'contact_email' => 'biuro@example.com']);
        $anna = User::create(['name' => 'Anna Nowak', 'email' => 'anna@example.com', 'password' => 'demo-haslo-123', 'client_id' => $client->id]);

        $t1 = $tickets->create($anna, ['title' => 'Formularz kontaktowy nie wysyła wiadomości', 'type' => 'bug'],
            "Od wczoraj formularz na stronie /kontakt pokazuje błąd po kliknięciu „Wyślij”.");
        $tickets->postMessage($t1, $admin, 'Dzięki, sprawdzam. Wygląda na problem z SMTP po aktualizacji wtyczki.', false);
        $tickets->postMessage($t1, $admin, 'Wtyczka WP Mail SMTP 4.2 — klucz API wygasł.', true);
        $tickets->changeStatus($t1, $admin, TicketStatus::NeedsDecision);
        $tickets->postMessage($t1, $admin, 'Potrzebuję decyzji: odnowić płatny plan wtyczki czy przejść na darmowy SMTP hostingu?', false);

        $t2 = $tickets->create($admin, ['client_id' => $client->id, 'title' => 'Artykuł: Jak dbać o storczyki zimą', 'type' => 'article'],
            'Szkic do akceptacji w przyszłym tygodniu.');
        $tickets->create($admin, ['client_id' => $client->id, 'title' => 'Opieka nad stroną — listopad', 'type' => 'site_review'], null);
        $tickets->changeStatus($t2, $admin, TicketStatus::Blocked, 'Czekamy na zdjęcia od fotografa');

        $this->command?->info("Admin: admin@example.com / demo-haslo-123, sekret 2FA: {$secret}");
        $this->command?->info('Klient: anna@example.com / demo-haslo-123');
    }
}
