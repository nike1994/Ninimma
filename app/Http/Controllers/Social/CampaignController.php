<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignAudience;
use App\Models\SocialAccount;
use App\Services\AudienceImport;
use App\Services\CampaignInsights;
use Carbon\CarbonImmutable;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    /** Kampanie marketingowe: lista z filtrami, wskaźniki, wybrana kampania (zakładki), ranking. */
    public function index(Request $request)
    {
        $clientId = $request->integer('client') ?: null;
        $status = $request->query('status', 'all');
        $groups = ['active' => ['active'], 'planned' => ['draft', 'review'], 'finished' => ['finished']];
        $all = Campaign::with(['client', 'audiences'])->withCount('contents')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->orderByRaw("case status when 'active' then 0 when 'review' then 1 when 'draft' then 2 else 3 end")
            ->latest('starts_on')->get();
        $counts = collect($groups)->map(fn ($st) => $all->whereIn('status', $st)->count());
        $list = $status !== 'all' && isset($groups[$status]) ? $all->whereIn('status', $groups[$status])->values() : $all;

        // Wyniki każdej kampanii (karty, ranking, wskaźniki).
        $insights = $all->mapWithKeys(fn ($c) => [$c->id => new CampaignInsights($c)]);
        $totals = $insights->map(fn ($i) => $i->totals());
        $goals = $insights->map(fn ($i, $id) => $i->goal($totals[$id]));
        $pacing = $insights->map(fn ($i, $id) => $i->pacing($totals[$id]));
        $sum = fn ($k, $ids = null) => ($ids ?? $list->pluck('id'))->sum(fn ($id) => $totals[$id][$k] ?? 0);
        $ids = $list->pluck('id');
        $spend = $sum('spend');
        $revenue = $sum('revenue');
        // Trend: przyrost w ostatnich 7 dniach vs 7 dni wcześniej (wszystkie kampanie z listy).
        $now = CarbonImmutable::today();
        $st = fn ($d) => $ids->reduce(fn ($c, $id) => array_map(fn ($a, $b) => $a + $b, $c, array_values($insights[$id]->stateAt($d))), [0, 0, 0]);
        [$a, $b, $c] = [$st($now), $st($now->subDays(7)), $st($now->subDays(14))];
        $pct = fn ($i) => ($b[$i] - $c[$i]) > 0 ? (int) round((($a[$i] - $b[$i]) - ($b[$i] - $c[$i])) / ($b[$i] - $c[$i]) * 100) : null;

        $selected = $list->firstWhere('id', $request->integer('c')) ?? $list->first();
        $sel = $selected ? $insights[$selected->id] : null;
        $tab = in_array($request->query('tab'), ['summary', 'settings', 'creatives', 'audience', 'results'], true) ? $request->query('tab') : 'summary';

        $accounts = SocialAccount::with('client')->where('is_active', true)->get();

        return view('social.campaigns.index', [
            'list' => $list, 'counts' => $counts, 'status' => $status, 'clientId' => $clientId, 'view' => $request->query('view') === 'grid' ? 'grid' : 'list',
            'totals' => $totals, 'insights' => $insights, 'goals' => $goals, 'pacing' => $pacing,
            'metaAds' => \App\Services\Social\MetaAdsSync::enabled(), 'adsSync' => \App\Services\Ads\AdsSync::enabled(),
            'kpi' => [
                'active' => $all->where('status', 'active')->count(),
                'reach' => $sum('reach'), 'reach_trend' => $pct(0),
                'clicks' => $sum('clicks'), 'clicks_trend' => $pct(1),
                'spend' => $spend, 'roas' => $spend > 0 && $revenue > 0 ? round($revenue / $spend, 1) : null,
            ],
            'brands' => $accounts->groupBy('client_id')->map(fn ($accs) => (object) ['client' => $accs->first()->client, 'platforms' => $accs->pluck('platform')->unique('value')])
                ->sortBy(fn ($b) => $b->client->name)->values(),
            'accountsCount' => $accounts->count(),
            'selected' => $selected, 'sel' => $sel, 'tab' => $tab,
            'taskSets' => \App\Models\TaskSet::orderByDesc('uses')->orderBy('name')->get(),
            // Wnioski z wcześniejszych kampanii tego klienta — pomoc przy planowaniu kolejnej.
            'lessons' => $selected ? Campaign::where('client_id', $selected->client_id)->where('id', '!=', $selected->id)
                ->where(fn ($q) => $q->whereNotNull('retro_worked')->orWhereNotNull('retro_failed')->orWhereNotNull('retro_next'))
                ->latest('retro_at')->limit(3)->get() : collect(),
            'selTotals' => $selected ? $totals[$selected->id] : null,
            // Ranking: realizacja celu (%), potem ROAS i konwersje.
            'ranking' => $list->sortByDesc(fn ($c) => [$goals[$c->id]->pct ?? -1, $totals[$c->id]['roas'] ?? 0, $totals[$c->id]['conversions']])->values(),
        ]);
    }

    /** Struktura odbiorców: ręcznie (płeć, wiek, miasta, kraje) — zastępuje poprzednie dane. */
    public function audience(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'female' => ['nullable', 'numeric', 'between:0,100'], 'male' => ['nullable', 'numeric', 'between:0,100'],
            'age' => ['array'], 'age.*' => ['nullable', 'numeric', 'between:0,100'],
            'cities' => ['nullable', 'string', 'max:2000'], 'countries' => ['nullable', 'string', 'max:2000'],
        ]);
        $rows = [];
        foreach (['female' => 'female', 'male' => 'male'] as $k => $label) {
            if (($data[$k] ?? null) !== null && $data[$k] !== '') {
                $rows[] = ['gender', $label, (float) $data[$k]];
            }
        }
        foreach (CampaignAudience::AGES as $age) {
            if (($data['age'][$age] ?? null) !== null && $data['age'][$age] !== '') {
                $rows[] = ['age', $age, (float) $data['age'][$age]];
            }
        }
        foreach (['city' => 'cities', 'country' => 'countries'] as $dim => $field) {
            foreach (self::parseShares($data[$field] ?? '') as $label => $share) {
                $rows[] = [$dim, $label, $share];
            }
        }
        $campaign->saveAudience($rows);

        return back()->with('status', __('ui.saved'));
    }

    /** Import podziału odbiorców z eksportu menedżera reklam (kolumny: wiek/płeć/region/kraj + zasięg lub wyświetlenia). */
    public function audienceImport(Request $request, Campaign $campaign)
    {
        $request->validate(['csv' => ['required', 'file', 'max:4096']]);
        $rows = AudienceImport::parse((string) $request->file('csv')->get());
        if (! $rows) {
            return back()->withErrors(['csv' => __('mkt.import_empty')]);
        }
        $campaign->saveAudience($rows, array_unique(array_column($rows, 0)), 'import');

        return back()->with('status', __('mkt.import_done', ['n' => count($rows)]));
    }

    /** „Warszawa: 18,4” / „Warszawa 18.4%” w kolejnych liniach → [label => %]. */
    /** Zestaw zadań dla istniejącej kampanii (z zakładki Ustawienia). */
    public function applyTaskSet(Request $request, Campaign $campaign, \App\Services\TaskSetService $sets)
    {
        $data = $request->validate(['task_set_id' => ['required', 'exists:task_sets,id'], 'start_on' => ['required', 'date']]);
        $set = \App\Models\TaskSet::findOrFail($data['task_set_id']);
        $n = $sets->apply($set, $campaign->client_id, \Carbon\Carbon::parse($data['start_on']), $request->user(), $campaign->id);

        return back()->with('status', __('task.set_applied', ['set' => $set->name, 'n' => $n]));
    }

    /** Wnioski po kampanii: co zadziałało, co nie, co następnym razem (opcjonalnie widoczne dla klienta). */
    public function retro(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'retro_worked' => ['nullable', 'string', 'max:3000'], 'retro_failed' => ['nullable', 'string', 'max:3000'],
            'retro_next' => ['nullable', 'string', 'max:3000'],
        ]);
        $campaign->fill($data + ['retro_shared' => $request->boolean('retro_shared')]);
        $campaign->forceFill(['retro_by' => $request->user()->id, 'retro_at' => now()])->save();

        return redirect()->to(route('social.campaigns.index', ['c' => $campaign->id]).'#retro')->with('status', __('ui.saved'));
    }

    /** Pobranie wyników reklam i odbiorców z Meta Ads dla jednej kampanii (przycisk). */
    public function sync(Campaign $campaign, \App\Services\Ads\AdsSync $sync)
    {
        if (! \App\Services\Ads\AdsSync::enabled()) {
            return back()->with('error', __('mkt.ads_sync_off'));
        }
        $r = $sync->syncCampaign($campaign);
        if ($r['ads'] === 0) {
            return back()->with('error', __('mkt.ads_no_ids'));
        }
        if ($r['ok'] === 0) {
            return back()->with('error', __('mkt.ads_failed', ['msg' => $r['errors'][0] ?? '—']));
        }

        return back()->with('status', __('mkt.ads_done', ['n' => $r['ok']]).($r['audience'] ? ' '.__('mkt.meta_audience_done') : ''));
    }

    public static function parseShares(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^\s*(.+?)[\s:;=-]+(\d+(?:[.,]\d+)?)\s*%?\s*$/u', $line, $m)) {
                $out[mb_substr(trim($m[1]), 0, 80)] = min(100, (float) str_replace(',', '.', $m[2]));
            }
        }

        return array_slice($out, 0, 20, true);
    }


    public function create(Request $request)
    {
        return $this->form(new Campaign(['client_id' => $request->integer('client') ?: null, 'starts_on' => today()]));
    }

    public function edit(Campaign $campaign)
    {
        return $this->form($campaign->load(['contents.publications.account']));
    }

    private function form(Campaign $campaign)
    {
        return view('social.campaigns.form', ['campaign' => $campaign, 'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'sets' => \App\Models\TaskSet::orderByDesc('uses')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate(['task_set_id' => ['nullable', 'exists:task_sets,id']]);
        $campaign = Campaign::create($this->validated($request));
        $msg = __('ui.saved');
        // Zestaw zadań od razu przy nowej kampanii (terminy od daty startu kampanii).
        if ($setId = $request->integer('task_set_id')) {
            $set = \App\Models\TaskSet::findOrFail($setId);
            $n = app(\App\Services\TaskSetService::class)->apply($set, $campaign->client_id, \Carbon\Carbon::parse($campaign->starts_on ?? today()), $request->user(), $campaign->id);
            $msg .= ' '.__('task.set_applied', ['set' => $set->name, 'n' => $n]);
        }

        return redirect()->route('social.campaigns.edit', $campaign)->with('status', $msg);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->update($this->validated($request));

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:200'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'budget' => ['required', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(Campaign::STATUSES)],
            'revenue' => ['nullable', 'numeric', 'min:0'],
            'leads' => ['nullable', 'integer', 'min:0'],
            'objective' => ['nullable', Rule::in(Campaign::OBJECTIVES)],
            'target_value' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
