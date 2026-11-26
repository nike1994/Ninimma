<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Content;
use App\Models\FileUsage;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\FileStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Demo social media: konta, treści z grafikami, publikacje i pomiary (tylko lokalnie). */
class SocialDemoSeeder extends Seeder
{
    public function run(FileStore $store): void
    {
        mt_srand(7);
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        $clients = Client::orderBy('id')->get()->values();
        $accounts = collect([
            [$clients[0], 'instagram', 'Zielony Dom', 'zielonydom_pl'],
            [$clients[0], 'facebook', 'Zielony Dom', 'zielonydom'],
            [$clients[1], 'instagram', 'FitLife', 'fitlife_pl'],
            [$clients[1], 'tiktok', 'FitLife', 'fitlife'],
            [$clients[1], 'youtube', 'FitLife', 'fitlifetv'],
            [$clients[2], 'instagram', 'Studio Blu', 'studioblu'],
            [$clients[2], 'linkedin', 'Studio Blu', 'studio-blu'],
        ])->map(fn ($a) => SocialAccount::create(['client_id' => $a[0]->id, 'platform' => $a[1], 'name' => $a[2], 'handle' => $a[3]]));

        $titles = ['Nowa kolekcja jesienna', 'Kulisy sesji zdjęciowej', 'Poradnik: 5 trików', 'Rabat na weekend', 'Opinia klientki',
            'Seria rolek — odcinek 1', 'Seria rolek — odcinek 2', 'Konkurs z nagrodami', 'Przed i po', 'Zespół przy pracy',
            'Q&A z ekspertem', 'Nowość w ofercie', 'Inspiracje na grudzień', 'Za kulisami magazynu', 'Podsumowanie miesiąca',
            'Unboxing paczki', 'Wywiad z założycielką', 'Świąteczny lookbook', 'Trend roku', 'Kalendarz adwentowy'];
        $palettes = [[[54, 180, 92], [19, 163, 180]], [[11, 106, 168], [123, 85, 214]], [[224, 69, 123], [250, 126, 30]], [[19, 163, 180], [11, 42, 74]], [[155, 225, 93], [54, 180, 92]]];
        $now = Carbon::now();

        foreach ($titles as $i => $title) {
            $client = $clients[$i % 3];
            $clientAccounts = $accounts->where('client_id', $client->id)->values();
            $format = $i % 4 === 1 ? 'video' : ($i % 5 === 2 ? 'carousel' : 'image');
            $content = Content::create([
                'client_id' => $client->id, 'title' => $title, 'created_by' => $admin->id,
                'description' => 'Krótki opis posta: '.mb_strtolower($title).'. Zapraszamy do komentowania!',
                'hashtags' => '#'.str_replace(' ', '', mb_strtolower($client->name)).' #nowosc #inspiracje',
                'type' => $format === 'video' ? 'reel' : 'post', 'format' => $format, 'stage' => 'ready',
            ]);
            $path = $this->image($palettes[$i % 5], $i);
            $file = $store->storeLocalFile($path, 'image/png');
            @unlink($path);
            FileUsage::create(['file_id' => $file->id, 'usable_type' => 'content', 'usable_id' => $content->id, 'name' => 'grafika-'.($i + 1).'.png', 'uploaded_by' => $admin->id]);

            // 14 publikacji w przeszłości (z pomiarami), reszta zaplanowana na najbliższe dni.
            $offset = $i < 14 ? -($i * 4 + 1) : ($i - 14) + 0;
            foreach ($clientAccounts->take($i % 2 ? 1 : 2) as $k => $acc) {
                $when = $now->copy()->addDays($offset)->setTime([9, 11, 13, 16, 18][($i + $k) % 5], [0, 30][$k % 2]);
                $published = $when->isPast();
                $pub = Publication::create([
                    'content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => $acc->platform->value,
                    'scheduled_at' => $when, 'published_at' => $published ? $when : null, 'status' => $published ? 'published' : 'planned',
                    'url' => $published ? 'https://example.com/p/'.$content->id.'-'.$acc->id : null,
                    'visible_to_client' => ! $published || $i % 3 === 0,
                ]);
                if ($published) {
                    $age = (int) $when->diffInDays($now);
                    $checks = collect(Publication::CHECK_DAYS)->filter(fn ($d) => $d <= $age)->values();
                    // Ostatni pomiar zostawiamy do wpisania (kolejka statystyk).
                    $checks = $i % 4 === 0 ? $checks->slice(0, -1) : $checks;
                    $base = mt_rand(1500, 9000) * ($acc->platform->value === 'tiktok' ? 3 : 1);
                    foreach ($checks as $n => $d) {
                        $f = [0.55, 0.85, 1.0][$n];
                        $pub->measurements()->create([
                            'user_id' => $admin->id, 'measured_on' => $when->copy()->addDays($d)->toDateString(),
                            'reach' => (int) ($base * $f), 'reactions' => (int) ($base * $f * 0.06), 'comments' => (int) ($base * $f * 0.008),
                            'shares' => (int) ($base * $f * 0.004), 'clicks' => (int) ($base * $f * 0.015),
                        ]);
                    }
                    $pub->forceFill(['checks_done' => $checks->count()])->save();
                }
            }
        }
        // Pomysły i treści w przygotowaniu (lejek).
        foreach (['Pomysł: konkurs na Mikołajki', 'Pomysł: seria porad zimowych', 'Pomysł: live z ekspertem', 'Pomysł: kampania noworoczna'] as $i => $t) {
            Content::create(['client_id' => $clients[$i % 3]->id, 'title' => $t, 'stage' => 'idea', 'created_by' => $admin->id]);
        }
        foreach (['Opisy produktów — nowa kolekcja', 'Rolka: kulisy pakowania', 'Karuzela: 5 inspiracji'] as $i => $t) {
            Content::create(['client_id' => $clients[$i % 3]->id, 'title' => $t, 'stage' => 'preparing', 'created_by' => $admin->id]);
        }
        // Kampanie, zadania social i notatki.
        $cmpData = [
            [$clients[0], 'Black Friday – Zielony Dom', 'active', 5000, 21000, null],
            [$clients[1], 'Świąteczna sprzedaż – FitLife', 'draft', 8000, null, null],
            [$clients[2], 'Zapisy na nowy sezon – Studio Blu', 'review', 3000, null, 187],
        ];
        foreach ($cmpData as $k => [$client, $name, $status, $budget, $revenue, $leads]) {
            $cmp = \App\Models\Campaign::create(['client_id' => $client->id, 'name' => $name, 'status' => $status, 'budget' => $budget,
                'revenue' => $revenue, 'leads' => $leads, 'starts_on' => $now->copy()->subDays(10)->toDateString(), 'ends_on' => $now->copy()->addDays(20)->toDateString(),
                'goal' => 'Zwiększyć sprzedaż i rozpoznawalność marki.']);
            Content::where('client_id', $client->id)->where('stage', 'ready')->limit(3)->update(['campaign_id' => $cmp->id]);
        }
        $team = User::where('role', 'admin')->orderBy('id')->get();
        $tickets = app(\App\Services\TicketService::class);
        foreach ([['Przygotować treści na grudniową kampanię', 0, 1], ['Zatwierdzenie grafik', 1, 2], ['Harmonogram postów na grudzień', 2, 3], ['Raport miesięczny — wszystkie konta', 0, 5]] as $k => [$title, $ci, $days]) {
            $t = $tickets->create($admin, ['client_id' => $clients[$ci]->id, 'title' => $title, 'type' => 'social',
                'due_on' => $now->copy()->addDays($days)->toDateString(), 'assigned_to' => $team[$k % $team->count()]->id], null);
            if ($k === 0) {
                $tickets->changeStatus($t, $admin, \App\Enums\TicketStatus::Done);
            }
        }
        foreach (['Start kampanii świątecznej – 1 grudnia', 'Nowa seria rolek FitLife (3 odcinki)', 'Aktualizacja opisów profili', 'Przygotować raport kwartalny'] as $body) {
            \App\Models\SocialNote::create(['user_id' => $admin->id, 'body' => $body]);
        }

        Publication::where('status', 'planned')->where('visible_to_client', true)->limit(1)->update(['client_status' => 'changes', 'client_comment' => 'Proszę o jaśniejsze zdjęcie.']);

        // Zdjęcia profilu kont (logo marki: kolorowe koło z inicjałem) — do podglądu postów.
        $colors = ['FitLife' => [224, 69, 123], 'Studio Blu' => [11, 106, 168], 'Zielony Dom' => [54, 180, 92]];
        foreach (SocialAccount::with('client')->get() as $acc) {
            [$r, $g, $b] = $colors[$acc->client->name] ?? [19, 163, 180];
            $im = imagecreatetruecolor(320, 320);
            imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
            imagefilledellipse($im, 160, 160, 236, 236, imagecolorallocate($im, 255, 255, 255));
            $font = resource_path('fonts/DejaVuSans-Bold.ttf');
            $letter = mb_substr($acc->client->name, 0, 1);
            $box = imagettfbbox(120, 0, $font, $letter);
            imagettftext($im, 120, 0, (int) (160 - ($box[2] - $box[0]) / 2 - $box[0]), (int) (160 + ($box[1] - $box[7]) / 2), imagecolorallocate($im, $r, $g, $b), $font, $letter);
            ob_start();
            imagepng($im);
            $acc->storeAvatar(ob_get_clean());
        }
    }

    /** Prosta grafika demonstracyjna: gradient + kształty. */
    private function image(array $palette, int $seed): string
    {
        [$a, $b] = $palette;
        $w = 480; $h = 360;
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($im, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t));
            imageline($im, 0, $y, $w, $y, $c);
        }
        $white = imagecolorallocatealpha($im, 255, 255, 255, 70);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 95);
        imagefilledellipse($im, 120 + ($seed * 37) % 240, 150, 220, 220, $soft);
        imagefilledellipse($im, 300, 260 - ($seed * 13) % 80, 160, 160, $white);
        imagefilledrectangle($im, 60, 280, 260 + ($seed * 11) % 120, 300, $white);
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';
        imagepng($im, $path);

        return $path;
    }
}
