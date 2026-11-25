<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Collaboration;
use App\Models\Content;
use App\Models\Influencer;
use App\Models\Publication;
use App\Models\Reminder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Demo influencerów (tylko lokalnie). */
class InfluencerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $people = [
            ['Ola Wiśniewska', ['uroda', 'eko'], [['instagram', '@ola.eko', 52000], ['tiktok', '@olaeko', 81000]], 5],
            ['Kuba Zieliński', ['fitness', 'sport'], [['youtube', 'KubaFit', 120000], ['instagram', '@kuba.fit', 34000]], 4],
            ['Marta Lis', ['dom', 'diy'], [['instagram', '@marta.diy', 18000], ['pinterest', 'martadiy', 9000]], 4],
            ['Tomek Nowicki', ['tech'], [['tiktok', '@tomektech', 64000]], 3],
            ['Ewa Mazur', ['moda', 'uroda'], [['instagram', '@ewamazur', 97000]], 5],
            ['Paweł Kot', ['kulinaria'], [['youtube', 'GotujZPawlem', 45000], ['facebook', 'pawelkot', 22000]], 4],
        ];
        $infs = collect($people)->map(fn ($p) => Influencer::create([
            'name' => $p[0], 'tags' => $p[1], 'rating' => $p[3], 'email' => mb_strtolower(explode(' ', $p[0])[0]).'@example.com',
            'address' => 'ul. Przykładowa 1, 00-001 Warszawa', 'parcel_locker' => 'WAW01M',
            'profiles' => array_map(fn ($x) => ['platform' => $x[0], 'handle' => $x[1], 'followers' => $x[2], 'url' => 'https://example.com/'.ltrim($x[1], '@')], $p[2]),
        ]));

        $campaigns = Campaign::orderBy('id')->get();
        $plan = [
            [0, 0, 1500, 6, 'invoice'], [1, 0, 2200, 4, 'contract'], [2, 1, 900, 1, 'contract'],
            [3, 1, 1200, 2, 'invoice'], [4, 2, 1800, 3, 'invoice'], [5, 2, 800, 5, 'contract'],
        ];
        $steps = ['contract_sent', 'contract_signed', 'brief_sent', 'brief_accepted', 'materials_complete', 'published', 'paid'];
        foreach ($plan as $k => [$ii, $ci, $rate, $progress, $billing]) {
            $c = Collaboration::create(['influencer_id' => $infs[$ii]->id, 'campaign_id' => $campaigns[$ci]->id, 'rate' => $rate, 'billing' => $billing, 'scope' => '1 rolka + 3 relacje', 'license_scope' => 'social media klienta, 12 mies.',
                'license_from' => $now->copy()->subDays(20)->toDateString(), 'license_to' => $now->copy()->addDays([10, 40, 90, 5, 200, 60][$k])->toDateString(),
                'discount_code' => strtoupper(explode(' ', $infs[$ii]->name)[0]).'10', 'sales' => $progress >= 5 ? rand(10, 80) : null]);
            $c->forceFill(['influencer_id' => $infs[$ii]->id, 'campaign_id' => $campaigns[$ci]->id])->save();
            $c->shipments()->create(['contents' => 'Zestaw produktów', 'quantity' => 1, 'value' => 250, 'tracking_number' => 'PL'.rand(100000, 999999),
                'sent_on' => $progress >= 2 ? $now->copy()->subDays(8)->toDateString() : null, 'delivered_on' => $progress >= 3 ? $now->copy()->subDays(5)->toDateString() : null]);
            $m = $c->materials()->create(['description' => 'Rolka 30 s z produktem', 'license_until' => $c->license_to]);
            $c->materials()->create(['description' => '3 relacje', 'license_until' => $c->license_to]);
            $fill = [];
            foreach (array_slice($steps, 0, min(4, $progress + 1)) as $s) {
                $fill[$s.'_at'] = $now->copy()->subDays(15);
            }
            if ($progress >= 4) {
                $c->materials()->update(['is_received' => true]);
                $fill['materials_complete_at'] = $now->copy()->subDays(3);
                $pub = Publication::where('status', 'published')->whereHas('content', fn ($q) => $q->where('client_id', $campaigns[$ci]->client_id))->inRandomOrder()->first();
                if ($pub) {
                    $m->update(['publication_id' => $pub->id]);
                }
            }
            if ($progress >= 5) {
                $fill['published_at'] = $now->copy()->subDays(2);
            }
            if ($progress >= 6) {
                $fill['paid_at'] = $now->copy()->subDay();
                $fill['status'] = 'done';
            }
            $c->forceFill($fill)->save();
        }
        Reminder::create(['remindable_type' => 'influencer', 'remindable_id' => $infs[2]->id, 'due_on' => today(), 'body' => 'Zapytać o termin nagrania']);
        Reminder::create(['remindable_type' => 'influencer', 'remindable_id' => $infs[3]->id, 'due_on' => today()->addDays(2), 'body' => 'Wysłać brief']);
    }
}
