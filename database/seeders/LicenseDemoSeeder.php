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

        // Przedłużona licencja z kosztem (trafia do kosztów kampanii).
        $long = $mats->first(fn ($m) => ! in_array($m->id, [$short->id, $organic->id, $soon?->id, $expired->id], true));
        if ($long) {
            app(LicenseGuard::class)->extend($long, $admin, $long->license_until->copy()->addMonths(3)->toDateString(), null, 400, 'Aneks nr 1 — +3 miesiące');
        }
    }
}
