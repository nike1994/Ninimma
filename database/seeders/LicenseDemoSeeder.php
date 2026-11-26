<?php

namespace Database\Seeders;

use App\Models\AdCampaign;
use App\Models\Content;
use App\Models\Material;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\LicenseGuard;
use Illuminate\Database\Seeder;

/** Demo strażnika licencji: różne stany liczników i kilka konfliktów do pokazania. */
class LicenseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $today = today();
        $mats = Material::with('collaboration.campaign')->orderBy('id')->get();
        $mats->filter(fn ($m) => str_contains($m->description, 'relacje'))->each(fn ($m) => $m->update(['kind' => 'story']));
        $mats->each(fn ($m) => $m->update(['license_from' => $m->collaboration->license_from, 'is_received' => true]));

        // Treść z materiału + publikacja zaplanowana po końcu licencji (licencja za 5 dni).
        $short = $mats->first(fn ($m) => $m->license_until && (int) $today->diffInDays($m->license_until, false) === 5) ?? $mats->first();
        $clientId = $short->collaboration->campaign->client_id;
        $account = SocialAccount::where('client_id', $clientId)->first();
        $content = Content::create(['client_id' => $clientId, 'title' => 'Rolka influencera — repost', 'created_by' => $admin->id,
            'campaign_id' => $short->collaboration->campaign_id, 'type' => 'reel', 'format' => 'video', 'stage' => 'ready',
            'description' => 'Repost rolki influencera z oznaczeniem autora.']);
        $content->materials()->attach($short->id);
        if ($account) {
            Publication::create(['content_id' => $content->id, 'social_account_id' => $account->id, 'platform' => $account->platform->value,
                'scheduled_at' => $today->copy()->addDays(3)->setTime(18, 0), 'status' => 'planned']);
            Publication::create(['content_id' => $content->id, 'social_account_id' => $account->id, 'platform' => $account->platform->value,
                'scheduled_at' => $today->copy()->addDays(9)->setTime(12, 0), 'status' => 'planned']);
        }

        // Materiał tylko do postów organicznych użyty w reklamie.
        $organic = $mats->where('collaboration.campaign_id', AdCampaign::whereNotNull('campaign_id')->value('campaign_id'))->last() ?? $mats->last();
        $organic->update(['license_channels' => ['organic', 'website']]);
        $ad = AdCampaign::where('status', 'active')->where('campaign_id', $organic->collaboration->campaign_id)->first() ?? AdCampaign::where('status', 'active')->first();
        $ad?->update(['material_id' => $organic->id]);

        // Reklama dłuższa niż licencja (ostrzeżenie).
        $soon = $mats->first(fn ($m) => $m->license_until && (int) $today->diffInDays($m->license_until, false) === 10 && $m->id !== $organic->id);
        if ($soon) {
            AdCampaign::create(['client_id' => $soon->collaboration->campaign->client_id, 'campaign_id' => $soon->collaboration->campaign_id,
                'platform' => 'meta', 'name' => 'Rolka influencera — dark post', 'objective' => 'video_views', 'budget' => 600, 'status' => 'planned',
                'starts_on' => $today->copy()->addDays(2)->toDateString(), 'ends_on' => $today->copy()->addDays(24)->toDateString(), 'material_id' => $soon->id]);
        }

        // Wygasła licencja z obowiązkiem zdjęcia opublikowanego posta.
        $expired = $mats->first(fn ($m) => $m->publication_id && ! in_array($m->id, [$short->id, $organic->id, $soon?->id], true)) ?? $mats->get(2);
        $expired->update(['license_from' => $today->copy()->subDays(95), 'license_until' => $today->copy()->subDays(3), 'requires_takedown' => true]);

        // UGC: licencja zaczyna się za tydzień; zdjęcia produktowe bez limitu czasu tylko na stronę i e-mail.
        $c = $mats->first()->collaboration;
        $c->materials()->create(['description' => 'UGC — unboxing (wideo pionowe)', 'kind' => 'video', 'is_received' => true,
            'license_from' => $today->copy()->addDays(7), 'license_until' => $today->copy()->addDays(97)]);
        $c->materials()->create(['description' => 'Zdjęcia produktowe od twórcy', 'kind' => 'photo', 'is_received' => true,
            'license_channels' => ['website', 'email', 'print']]);

        $this->attachDemoFiles($admin);

        // Przedłużona licencja z kosztem (trafia do kosztów kampanii).
        $long = $mats->first(fn ($m) => ! in_array($m->id, [$short->id, $organic->id, $soon?->id, $expired->id], true));
        if ($long) {
            app(LicenseGuard::class)->extend($long, $admin, $long->license_until->copy()->addMonths(3)->toDateString(), null, 400, 'Aneks nr 1 — +3 miesiące');
        }
    }

    /** Krótkie pionowe filmy (ffmpeg, jeśli jest) i kadry zdjęć jako pliki materiałów — żeby było widać miniatury. */
    private function attachDemoFiles(User $admin): void
    {
        $store = app(\App\Services\FileStore::class);
        $palettes = [['13a3b4', '9be15d'], ['0b6aa8', '7fb8ff'], ['e0457b', 'ffc24b'], ['7b55d6', '13a3b4'], ['36b45c', 'feda75'], ['0e3a63', 'e08a00']];
        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        foreach (Material::whereNull('file_usage_id')->where('is_received', true)->orderBy('id')->get() as $i => $m) {
            [$a, $b] = $palettes[$i % count($palettes)];
            $tmp = tempnam(sys_get_temp_dir(), 'demo');
            $video = $m->kind !== 'photo' && $ffmpeg !== '';
            if ($video) {
                $out = $tmp.'.mp4';
                $shape = $i % 2 ? 'drawbox=x=55:y=150:w=160:h=200:color=white@0.35:t=fill' : 'drawbox=x=35:y=300:w=200:h=110:color=white@0.4:t=fill';
                exec(escapeshellarg($ffmpeg).' -v error -y -f lavfi -i '.escapeshellarg("gradients=s=270x480:c0=0x{$a}:c1=0x{$b}:speed=0.04:d=4,format=yuv420p")
                    .' -vf '.escapeshellarg($shape).' -t 4 -c:v libx264 -an '.escapeshellarg($out), $o, $code);
                @unlink($tmp);
                if ($code !== 0 || ! is_file($out)) {
                    continue;
                }
                $tmp = $out;
            } else {
                $im = imagecreatetruecolor(360, 360);
                for ($y = 0; $y < 360; $y++) {
                    $t = $y / 360;
                    $c = array_map(fn ($k) => (int) (hexdec(substr($a, $k, 2)) * (1 - $t) + hexdec(substr($b, $k, 2)) * $t), [0, 2, 4]);
                    imageline($im, 0, $y, 360, $y, imagecolorallocate($im, ...$c));
                }
                imagefilledellipse($im, 180, 190, 170, 170, imagecolorallocatealpha($im, 255, 255, 255, 70));
                imagepng($im, $tmp);
            }
            $file = $store->storeLocalFile($tmp, $video ? 'video/mp4' : 'image/png');
            if ($video) {
                // Kadr jak z przeglądarki przy wgrywaniu.
                $jpg = $tmp.'.jpg';
                exec(escapeshellarg($ffmpeg).' -v error -y -ss 1 -i '.escapeshellarg($tmp).' -frames:v 1 '.escapeshellarg($jpg), $o2, $c2);
                if ($c2 === 0 && is_file($jpg)) {
                    $file->forceFill(['poster_id' => $store->storeLocalFile($jpg, 'image/jpeg')->id])->save();
                    @unlink($jpg);
                }
            }
            @unlink($tmp);
            $file->forceFill(['mime' => $video ? 'video/mp4' : 'image/png'])->save();
            $name = \Illuminate\Support\Str::slug($m->description).($video ? '.mp4' : '.png');
            $u = \App\Models\FileUsage::create(['file_id' => $file->id, 'usable_type' => 'material', 'usable_id' => $m->id, 'name' => $name, 'uploaded_by' => $admin->id]);
            $m->update(['file_usage_id' => $u->id]);
        }
    }
}
