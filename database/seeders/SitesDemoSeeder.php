<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\Client;
use App\Models\Site;
use App\Models\SiteLog;
use App\Models\User;
use App\Services\SiteService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Demo stron, przeglądów i SEO (tylko lokalnie). */
class SitesDemoSeeder extends Seeder
{
    public function run(SiteService $sites, TicketService $tickets): void
    {
        $now = Carbon::now();
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        $wp = Checklist::create(['name' => 'WordPress — przegląd miesięczny', 'platform' => 'wordpress', 'items' => __('site.default_checklist')]);
        $clients = Client::orderBy('id')->get();
        $data = [
            [$clients[0], 'zielonydom.pl', 'woocommerce', 'cyber_Folks', 10],
            [$clients[1], 'fitlife.pl', 'wordpress', 'home.pl', 6],
            [$clients[2], 'studioblu.pl', 'wordpress', 'OVH', 4],
        ];
        foreach ($data as $k => [$client, $domain, $platform, $hosting, $hours]) {
            $site = Site::create(['client_id' => $client->id, 'domain' => $domain, 'platform' => $platform, 'hosting' => $hosting, 'care_hours' => $hours,
                'checklist_id' => $wp->id, 'review_recurrence' => 'monthly', 'next_review_on' => $now->copy()->startOfMonth()->toDateString()]);
            $site->deadlines()->create(['kind' => 'domain', 'name' => $domain, 'due_on' => $now->copy()->addDays([12, 45, 200][$k]), 'cost' => 60]);
            $site->deadlines()->create(['kind' => 'ssl', 'name' => 'Let\'s Encrypt', 'due_on' => $now->copy()->addDays([25, 8, 70][$k])]);
            $site->deadlines()->create(['kind' => 'license', 'name' => 'Elementor Pro', 'due_on' => $now->copy()->addDays([90, 20, 140][$k]), 'cost' => 250]);
            $review = $sites->scheduleReview($site, $admin);
            if ($k === 0) {
                $sites->completeReview($review, $admin, [0 => true, 1 => true, 2 => true, 3 => false, 4 => true, 5 => true, 6 => true], 'Szybkość strony głównej do poprawy (LCP 3,1 s).');
                $sites->problemFromReview($review, $admin, 'Poprawa szybkości strony głównej');
            }
            foreach ([['update', 'Aktualizacja WordPress 6.8 i 14 wtyczek', 18], ['deploy', 'Nowy formularz zapisu do newslettera', 9], ['restore', 'Test przywrócenia kopii zapasowej — OK', 4]] as [$kind, $desc, $ago]) {
                SiteLog::create(['site_id' => $site->id, 'user_id' => $admin->id, 'logged_on' => $now->copy()->subDays($ago)->toDateString(), 'kind' => $kind, 'description' => $desc]);
            }
            $t = $tickets->create($admin, ['client_id' => $client->id, 'site_id' => $site->id, 'title' => 'Aktualizacje i drobne poprawki — '.$domain, 'type' => 'task'], null);
            $tickets->logTime($t, $admin, [300, 200, 90][$k], $now->copy()->subDays(3)->toDateString(), 'Aktualizacje');

            foreach ([['sklep '.explode('.', $domain)[0], 1], [explode('.', $domain)[0].' opinie', 2], ['najlepsze '.['rośliny doniczkowe', 'treningi w domu', 'sesje zdjęciowe'][$k], 1]] as $j => [$phrase, $prio]) {
                $p = $site->phrases()->create(['phrase' => $phrase, 'priority' => $prio, 'target_url' => '/'.str_replace(' ', '-', $phrase)]);
                $pos = rand(8, 40);
                foreach ([60, 45, 30, 15, 2] as $ago) {
                    $p->positions()->create(['measured_on' => $now->copy()->subDays($ago)->toDateString(), 'position' => max(1, $pos)]);
                    $pos -= rand(-2, 6);
                }
            }
            foreach (['Poradnik zimowy', 'Ranking produktów'] as $j => $title) {
                $ticket = $tickets->create($admin, ['client_id' => $client->id, 'site_id' => $site->id, 'type' => 'seo_content', 'title' => $title.' — '.$domain,
                    'due_on' => $now->copy()->addMonthNoOverflow()->startOfMonth()->addDays(5 + $j * 10)->toDateString()], null);
                $site->seoContents()->create(['working_title' => $title, 'ticket_id' => $ticket->id, 'seo_phrase_id' => $site->phrases()->first()->id,
                    'due_on' => $ticket->due_on]);
            }
            $site->seoTasks()->create(['description' => 'Przekierowania 301 ze starych adresów', 'priority' => 1, 'status' => 'doing']);
            $site->seoTasks()->create(['description' => 'Dane strukturalne produktów', 'priority' => 2]);
            $site->backlinks()->create(['url' => 'https://blog.example.com/artykul-'.$k, 'placed_on' => $now->copy()->subDays(40), 'cost' => 300, 'checked_on' => $now->copy()->subDays(2)]);
        }
    }
}
