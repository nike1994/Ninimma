<?php

namespace App\Http\Controllers\Influencers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Material;
use App\Services\CampaignReport;
use Illuminate\Http\Request;

/** Biblioteka materiałów (administrator: wszystko; klient: gotowe materiały i termin licencji) + raport kampanii. */
class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $materials = Material::with(['collaboration.influencer.photo', 'collaboration.campaign.client', 'file.file', 'publication.account'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('is_received', true)
                ->whereHas('collaboration.campaign', fn ($c) => $c->where('client_id', $user->client_id)))
            ->when($request->query('license') === 'expired', fn ($q) => $q->whereDate('license_until', '<', today()))
            ->when($request->query('license') === 'soon', fn ($q) => $q->whereDate('license_until', '>=', today())->whereDate('license_until', '<=', today()->addDays(30)))
            ->latest()->paginate(40)->withQueryString();

        return view('influencers.materials', compact('materials'));
    }

    public function report(Request $request, Campaign $campaign)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $campaign->client_id === $user->client_id, 403);
        $report = new CampaignReport($campaign);

        return view('influencers.report', [
            'campaign' => $campaign->load('client'),
            'report' => $report,
            'costs' => $report->costs(),
            'results' => $report->results(),
            'summary' => $report->summary(),
            'rows' => $user->isAdmin() ? $report->byInfluencer() : collect(),
            'formats' => $report->byFormat(),
            'paid' => $report->paid(),
            'organic' => $report->organic(),
            'ads' => $campaign->ads()->with('latestResult')->get(),
            'full' => $user->isAdmin(),
        ]);
    }

    public function clientReports(Request $request)
    {
        return view('influencers.client-reports', [
            'campaigns' => Campaign::where('client_id', $request->user()->client_id)->where('status', '!=', 'draft')->latest('starts_on')->get(),
        ]);
    }
}
