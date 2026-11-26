<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Client;
use App\Services\CampaignInsights;
use Illuminate\Http\Request;

/**
 * Kampanie oczami klienta: wyniki wg celu, realizacja celu, wykres, kreacje i odbiorcy.
 * Bez rzeczy wewnętrznych (tempo budżetu, stawki influencerów, godziny, szkice). Administrator może
 * otworzyć podgląd dowolnego klienta (?client=).
 */
class ClientCampaignController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            $client = Client::find($request->integer('client'));
            if (! $client) {
                return redirect()->route('social.campaigns.index');
            }
        } else {
            $client = $user->client;
        }
        abort_unless($client, 403);

        $campaigns = Campaign::with('client', 'audiences')->where('client_id', $client->id)->whereIn('status', ['review', 'active', 'finished'])
            ->orderByRaw("case status when 'active' then 0 when 'review' then 1 else 2 end")->latest('starts_on')->get();
        $insights = $campaigns->mapWithKeys(fn ($c) => [$c->id => new CampaignInsights($c)]);
        $totals = $insights->map(fn ($i) => $i->totals());
        $goals = $insights->map(fn ($i, $id) => $i->goal($totals[$id]));
        $selected = $campaigns->firstWhere('id', $request->integer('c')) ?? $campaigns->first();

        return view('social.campaigns.client', [
            'client' => $client, 'campaigns' => $campaigns, 'insights' => $insights, 'totals' => $totals, 'goals' => $goals,
            'selected' => $selected, 'sel' => $selected ? $insights[$selected->id] : null,
            'preview' => $user->isAdmin(),
        ]);
    }
}
