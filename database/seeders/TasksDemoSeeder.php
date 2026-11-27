<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

/** Dane demo zadań i czasu pracy: terminy, szacunki, przydziały, tydzień pracy zespołu, stawki klientów, działający stoper. */
class TasksDemoSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(7);
        $team = User::where('role', Role::Admin)->orderBy('id')->get();
        $admin = $team->first();
        $tickets = app(TicketService::class);
        Client::where('name', 'FitLife')->update(['hourly_rate' => 180]);
        Client::where('name', 'Studio Blu')->update(['hourly_rate' => 140]);

        // Szybkie zadania z różnymi terminami.
        $add = [
            ['Zielony Dom', 'Grafiki na Black Friday — 3 formaty', -1, 0, 'high', 150],
            ['FitLife', 'Scenariusz rolki z instruktorką', 0, 0, 'normal', 90],
            ['Studio Blu', 'Raport miesięczny social media', 0, 1, 'normal', 60],
            ['FitLife', 'Akceptacja harmonogramu grudnia', 2, 0, 'urgent', 30],
            ['Zielony Dom', 'Opisy produktów — kolekcja zimowa', 4, 2, 'normal', 240],
            ['Studio Blu', 'Przegląd kampanii zapisów', 9, 3, 'normal', 45],
            ['FitLife', 'Pomysły na styczeń (burza mózgów)', null, 1, 'normal', null],
        ];
        foreach ($add as [$client, $title, $due, $who, $prio, $est]) {
            $t = $tickets->create($admin, ['title' => $title, 'client_id' => Client::where('name', $client)->value('id'), 'type' => 'task',
                'due_on' => $due === null ? null : today()->addDays($due)->toDateString(), 'assigned_to' => $team[$who % $team->count()]->id, 'priority' => $prio], null);
            $t->forceFill(['estimate_minutes' => $est])->save();
        }

        // Szacunki dla części istniejących zleceń.
        Ticket::open()->whereNull('estimate_minutes')->inRandomOrder()->limit(6)->get()
            ->each(fn ($t) => $t->forceFill(['estimate_minutes' => mt_rand(2, 16) * 30])->save());

        // Tydzień pracy zespołu (dni robocze do dziś), część wewnętrzna.
        $open = Ticket::open()->get();
        $monday = today()->startOfWeek();
        foreach ($team as $i => $u) {
            for ($d = $monday->copy(); $d->lte(today()) && $d->lt($monday->copy()->addDays(5)); $d->addDay()) {
                foreach (range(1, mt_rand(2, 4)) as $n) {
                    TimeEntry::create(['ticket_id' => $open->random()->id, 'user_id' => $u->id, 'spent_on' => $d->toDateString(),
                        'minutes' => mt_rand(2, 9) * 15, 'note' => ['Projekt graficzny', 'Copy i hashtagi', 'Poprawki po uwagach', 'Spotkanie z klientem', 'Analiza wyników'][mt_rand(0, 4)],
                        'billable' => mt_rand(0, 9) > 1, 'source' => mt_rand(0, 1) ? 'timer' : 'manual']);
                }
            }
        }

        // Działający stoper administratora (widać go w górnym pasku).
        $running = Ticket::where('title', 'Scenariusz rolki z instruktorką')->first();
        $admin->forceFill(['timer_ticket_id' => $running?->id, 'timer_started_at' => now()->subMinutes(47)])->save();
    }
}
