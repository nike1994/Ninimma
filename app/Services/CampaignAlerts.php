<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\User;
use App\Notifications\BudgetPacingAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Codzienny przegląd tempa budżetu aktywnych kampanii — e-mail do administratorów (ten sam alert najwyżej raz w tygodniu). */
class CampaignAlerts
{
    public function sendPacingAlerts(): int
    {
        $items = collect();
        Campaign::with('client')->where('status', 'active')->get()->each(function (Campaign $c) use ($items) {
            $ins = new CampaignInsights($c);
            $p = $ins->pacing($ins->totals());
            if (! $p || $p->state === 'ok') {
                return;
            }
            $recent = DB::table('campaign_alerts')->where('campaign_id', $c->id)->where('kind', $p->state)
                ->whereDate('sent_on', '>', today()->subDays(7))->exists();
            if ($recent) {
                return;
            }
            DB::table('campaign_alerts')->insert(['campaign_id' => $c->id, 'kind' => $p->state, 'sent_on' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
            $items->push(['campaign' => $c->name, 'client' => $c->client->name, 'state' => __('mkt.pace_'.$p->state),
                'sp' => $p->spend_pct, 'tp' => $p->time_pct, 'sum' => number_format($p->projected, 0, ',', ' ').' zł']);
        });
        if ($items->isNotEmpty()) {
            Notification::send(User::where('role', Role::Admin)->where('is_active', true)->get(), new BudgetPacingAlert($items));
        }

        return $items->count();
    }
}
