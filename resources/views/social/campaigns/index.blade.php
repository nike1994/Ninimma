@extends('layouts.app')
@section('title', __('mkt.title'))
@section('content')
@php
    $q = fn (array $over = []) => array_filter(array_merge(['client' => $clientId, 'status' => $status !== 'all' ? $status : null, 'view' => $view !== 'list' ? $view : null, 'c' => $selected?->id], $over), fn ($v) => $v !== null && $v !== '');
    $short = fn ($v) => \App\Support\Num::short((int) $v);
    $zl = fn ($v) => number_format((float) $v, 2, ',', ' ').' zł';
    // Nazwa kampanii często zawiera już klienta — nie powtarzamy go
    $withClient = fn ($c) => \Illuminate\Support\Str::contains(mb_strtolower($c->name), mb_strtolower($c->client->name)) ? null : $c->client->name;
    $pct = fn ($v) => $v === null ? '—' : str_replace('.', ',', $v > 0 && $v < 1 ? number_format($v, 2) : (string) round($v, 1));
    $trend = fn ($v) => $v === null ? '' : '<span class="trend-pill'.($v < 0 ? ' down' : '').'">'.($v < 0 ? '↓ ' : '↑ +').abs($v).'%</span>';
@endphp
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="megaphone" :size="26" /></span>
        <div><h1>{{ __('mkt.title') }}</h1><p class="muted">{{ __('mkt.subtitle') }}</p></div></div>
</div>

<div class="social-filters">
    <div class="chips">
        <a href="{{ route('social.campaigns.index', $q(['client' => null, 'c' => null])) }}" @class(['chip', 'chip-dark' => ! $clientId])>{{ __('social.all_accounts') }} <span>{{ $accountsCount }}</span></a>
        @foreach($brands as $b)
            <a href="{{ route('social.campaigns.index', $q(['client' => $b->client->id, 'c' => null])) }}" @class(['chip', 'chip-dark' => $clientId === $b->client->id])>
                <x-avatar :name="$b->client->name" :size="24" />{{ $b->client->name }} <span class="mk-chip-icons">@foreach($b->platforms as $pl)<x-platform :platform="$pl" :size="16" />@endforeach</span></a>
        @endforeach
        <a href="{{ route('social.accounts') }}" class="chip chip-add"><x-icon name="plus" :size="15" />{{ __('social.add_account') }}</a>
    </div>
</div>

<div class="kpis mk-kpis">
    <div class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="megaphone" :size="24" /></span><div><span class="kpi-label">{{ __('mkt.kpi_active') }}</span><div class="kpi-value"><strong>{{ $kpi['active'] }}</strong></div></div></div><x-waves variant="card" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="play" :size="24" /></span><div><span class="kpi-label">{{ __('mkt.kpi_reach') }}</span><div class="kpi-value"><strong>{{ $short($kpi['reach']) }}</strong>{!! $trend($kpi['reach_trend']) !!}</div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="cursor" :size="24" /></span><div><span class="kpi-label">{{ __('mkt.kpi_clicks') }}</span><div class="kpi-value"><strong>{{ $short($kpi['clicks']) }}</strong>{!! $trend($kpi['clicks_trend']) !!}</div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="layers" :size="24" /></span><div><span class="kpi-label">{{ __('mkt.kpi_spend') }}</span><div class="kpi-value"><strong title="{{ $zl($kpi['spend']) }}">{{ $kpi['spend'] >= 10000 ? \App\Support\Num::short((int) $kpi['spend']).' zł' : $zl($kpi['spend']) }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="bar-chart" :size="24" /></span><div><span class="kpi-label">ROAS <span class="mk-info" title="{{ __('mkt.roas_hint') }}">ⓘ</span></span><div class="kpi-value"><strong>{{ $kpi['roas'] !== null ? str_replace('.', ',', $kpi['roas']).' ×' : '—' }}</strong></div></div></div><x-waves variant="kpi" /></div>
</div>
<p class="muted small mk-trend-note">{{ __('mkt.trend_note') }}</p>

<div class="mk-toolbar">
    <div class="chips">
        <a href="{{ route('social.campaigns.index', $q(['status' => null, 'c' => null])) }}" @class(['chip', 'chip-dark' => $status === 'all'])>{{ __('mkt.filter_all') }}</a>
        @foreach(['active', 'planned', 'finished'] as $f)
            <a href="{{ route('social.campaigns.index', $q(['status' => $f, 'c' => null])) }}" @class(['chip', 'chip-dark' => $status === $f])><i class="mk-dot mk-dot-{{ $f }}"></i>{{ __('mkt.filter_'.$f) }} <span>{{ $counts[$f] }}</span></a>
        @endforeach
    </div>
    <div class="mk-toolbar-right">
        <span class="mk-view">
            <a href="{{ route('social.campaigns.index', $q(['view' => null])) }}" @class(['on' => $view === 'list']) aria-label="{{ __('mkt.view_list') }}"><x-icon name="list" :size="18" /></a>
            <a href="{{ route('social.campaigns.index', $q(['view' => 'grid'])) }}" @class(['on' => $view === 'grid']) aria-label="{{ __('mkt.view_grid') }}"><x-icon name="grid" :size="18" /></a>
        </span>
        <a href="{{ route('social.campaigns.create', array_filter(['client' => $clientId])) }}" class="btn btn-send"><x-icon name="plus" :size="16" />{{ __('mkt.new') }}</a>
    </div>
</div>

@if($list->isEmpty())
    <section class="card"><p class="muted">{{ __('social.no_campaigns') }}</p></section>
@elseif($view === 'grid')
    <div class="mk-grid">@foreach($list as $c)@include('social.campaigns._card', ['c' => $c, 'i' => $insights[$c->id], 't' => $totals[$c->id], 'active' => false])@endforeach</div>
@else
<div class="mk-layout">
    <div class="mk-list">@foreach($list as $c)@include('social.campaigns._card', ['c' => $c, 'i' => $insights[$c->id], 't' => $totals[$c->id], 'active' => $selected?->id === $c->id])@endforeach</div>

    @if($selected)
    @php($t = $selTotals)
    @php($tr = $sel->trends())
    <section class="card mk-detail" id="detail">
        <div class="mk-detail-head">
            <span class="mk-card-icons">@foreach($sel->platformKeys() as $pk)<x-platform :platform="$pk" :size="22" />@endforeach</span>
            <h2>{{ $selected->name }}@if($withClient($selected)) <span class="muted">– {{ $selected->client->name }}</span>@endif</h2>
            <span class="mk-status mk-{{ $selected->status }}">{{ __('mkt.status_'.$selected->status) }}</span>
            <span class="mk-spacer"></span>
            <a href="{{ route('reports.campaign', $selected) }}" class="btn btn-small"><x-icon name="bar-chart" :size="14" /> {{ __('mkt.report') }}</a>
            <a href="{{ route('social.campaigns.edit', $selected) }}" class="btn btn-small"><x-icon name="pencil" :size="14" /> {{ __('mkt.edit') }}</a>
        </div>
        <nav class="mk-tabs">
            @foreach(['summary', 'settings', 'creatives', 'audience', 'results'] as $tb)
                <a href="{{ route('social.campaigns.index', $q(['tab' => $tb === 'summary' ? null : $tb])) }}#detail" @class(['on' => $tab === $tb])>{{ __('mkt.tab_'.$tb) }}</a>
            @endforeach
        </nav>

        @if($tab === 'summary')
            @php($cover = $sel->cover())
            <div class="mk-sum">
                <div class="mk-cover">@if($cover)<img src="{{ route('files.show', [$cover, $cover->name]) }}" alt="">@endif
                    <span class="mk-cover-text"><small>{{ $selected->client->name }}</small><strong>{{ $selected->name }}</strong></span></div>
                <div class="mk-results">
                    <h3>{{ __('mkt.results') }}</h3>
                    <div class="mk-metrics">
                        <div><small>{{ __('mkt.reach') }}</small><strong>{{ $short($t['reach']) }}</strong>{!! $trend($tr['reach']) !!}</div>
                        <div><small>{{ __('mkt.engagement') }}</small><strong>{{ $short($t['engagement']) }}</strong>{!! $trend($tr['engagement']) !!}</div>
                        <div><small>{{ __('mkt.clicks') }}</small><strong>{{ number_format($t['clicks'], 0, ',', ' ') }}</strong>{!! $trend($tr['clicks']) !!}</div>
                        <div><small>CTR</small><strong>{{ $t['ctr'] !== null ? str_replace('.', ',', $t['ctr']).'%' : '—' }}</strong></div>
                    </div>
                    <div class="mk-chart-head"><h3>{{ __('mkt.over_time') }}</h3><span class="mk-legend"><i class="r"></i>{{ __('mkt.reach') }} <i class="c"></i>{{ __('mkt.clicks') }}</span></div>
                    @include('social.campaigns._chart', ['s' => $sel->series()])
                </div>
            </div>
            <div class="mk-trio">
                <div class="mk-box">
                    <h3><x-icon name="calendar" :size="18" /> {{ __('mkt.budget_schedule') }}</h3>
                    @php($daysTotal = $selected->starts_on && $selected->ends_on ? (int) $selected->starts_on->diffInDays($selected->ends_on) + 1 : null)
                    <p class="mk-line"><x-icon name="calendar" :size="14" /> @if($selected->starts_on){{ $selected->starts_on->translatedFormat('j M') }} – {{ $selected->ends_on?->translatedFormat('j M Y') ?? '…' }}@if($daysTotal) <span class="muted">({{ trans_choice('mkt.days', $daysTotal, ['n' => $daysTotal]) }})</span>@endif @else — @endif</p>
                    <p class="mk-line"><x-icon name="layers" :size="14" /> {{ __('mkt.budget') }}: <strong>{{ $zl($selected->budget) }}</strong></p>
                    @php($pctUsed = (float) $selected->budget > 0 ? (int) round($t['spend'] / (float) $selected->budget * 100) : null)
                    <i class="mk-bar"><i style="width: {{ min(100, $pctUsed ?? 0) }}%" @class(['over' => ($pctUsed ?? 0) > 100])></i></i>
                    <small class="muted">{{ __('mkt.spent') }}: {{ $zl($t['spend']) }}@if($pctUsed !== null) ({{ $pctUsed }}%)@endif</small>
                </div>
                <div class="mk-box">
                    <h3><x-icon name="share" :size="18" /> {{ __('mkt.platforms') }}</h3>
                    @forelse($sel->platforms()->take(4) as $pf)
                        <div class="mk-platform">@if($pf->platform)<x-platform :platform="$pf->platform" :size="30" />@else<span class="mk-pf-other">{{ mb_strtoupper(mb_substr($pf->key, 0, 1)) }}</span>@endif
                            <span><strong>{{ $pf->platform?->label() ?? ucfirst($pf->key) }}</strong><small class="muted">{{ __('mkt.reach') }}: {{ $short($pf->reach) }} · {{ __('mkt.clicks') }}: {{ number_format($pf->clicks, 0, ',', ' ') }}</small></span></div>
                    @empty<p class="muted small">{{ __('mkt.no_data') }}</p>@endforelse
                </div>
                <div class="mk-box">
                    <h3><x-icon name="flag" :size="18" /> {{ __('mkt.goal') }}</h3>
                    @if($selected->goal)<p class="mk-goal"><x-icon name="check-circle" :size="18" /> {{ $selected->goal }}</p>
                    @else<p class="muted small">{{ __('mkt.no_goal') }} <a href="{{ route('social.campaigns.edit', $selected) }}">{{ __('mkt.edit') }} →</a></p>@endif
                </div>
            </div>

        @elseif($tab === 'settings')
            <dl class="mk-settings">
                <dt>{{ __('ui.client') }}</dt><dd>{{ $selected->client->name }}</dd>
                <dt>{{ __('mkt.status') }}</dt><dd>{{ __('mkt.status_'.$selected->status) }}</dd>
                <dt>{{ __('mkt.dates') }}</dt><dd>{{ $selected->starts_on?->format('d.m.Y') ?? '—' }} – {{ $selected->ends_on?->format('d.m.Y') ?? '—' }}</dd>
                <dt>{{ __('mkt.budget') }}</dt><dd>{{ $zl($selected->budget) }}</dd>
                <dt>{{ __('mkt.goal') }}</dt><dd>{{ $selected->goal ?: '—' }}</dd>
                <dt>{{ __('mkt.utm') }}</dt><dd>{{ $selected->utmLinks()->count() }}</dd>
            </dl>
            <a href="{{ route('social.campaigns.edit', $selected) }}" class="btn btn-dark btn-small"><x-icon name="pencil" :size="14" /> {{ __('mkt.edit') }}</a>

        @elseif($tab === 'creatives')
            <div class="mk-creatives-all">
                @forelse($sel->creatives(50) as $cr)
                    <a href="{{ $cr->url }}" class="mk-creative">@if($cr->thumb)<img src="{{ route('files.show', [$cr->thumb, $cr->thumb->name]) }}" alt="">@else<span class="mk-creative-empty"><x-icon name="image" :size="20" /></span>@endif
                        <span><strong>{{ $cr->name }}</strong><small class="muted">{{ $cr->sub }}</small><small>CTR: <b>{{ str_replace('.', ',', $cr->ctr) }}%</b> · {{ __('mkt.clicks') }}: <b>{{ number_format($cr->clicks, 0, ',', ' ') }}</b></small></span></a>
                @empty<p class="muted">{{ __('mkt.no_creatives') }}</p>@endforelse
            </div>
            <div class="head-actions" style="margin-top: 1rem">
                <a href="{{ route('social.contents.create', ['client' => $selected->client_id, 'campaign' => $selected->id]) }}" class="btn btn-small"><x-icon name="plus" :size="14" /> {{ __('social.new_post') }}</a>
                <a href="{{ route('social.ads.create', ['client' => $selected->client_id, 'campaign' => $selected->id]) }}" class="btn btn-small"><x-icon name="zap" :size="14" /> {{ __('ads.new') }}</a>
            </div>

        @elseif($tab === 'audience')
            @php($aud = $sel->audience())
            <p class="muted small">{{ __('mkt.audience_hint') }}</p>
            <form method="post" action="{{ route('social.campaigns.audience', $selected) }}" class="mk-aud-form">
                @csrf @method('put')
                <fieldset><legend>{{ __('mkt.gender') }} (%)</legend>
                    <label>{{ __('mkt.female') }} <input type="number" name="female" min="0" max="100" step="0.1" value="{{ $aud['gender']['female'] ?? '' }}"></label>
                    <label>{{ __('mkt.male') }} <input type="number" name="male" min="0" max="100" step="0.1" value="{{ $aud['gender']['male'] ?? '' }}"></label></fieldset>
                <fieldset><legend>{{ __('mkt.age') }} (%)</legend>
                    @foreach(\App\Models\CampaignAudience::AGES as $age)<label>{{ $age }} <input type="number" name="age[{{ $age }}]" min="0" max="100" step="0.1" value="{{ $aud['age'][$age] ?? '' }}"></label>@endforeach</fieldset>
                <fieldset><legend>{{ __('mkt.top_cities') }}</legend>
                    <textarea name="cities" rows="8" placeholder="Warszawa: 18,4&#10;Kraków: 10,8">{{ collect($aud['city'])->map(fn ($v, $k) => $k.': '.str_replace('.', ',', $v))->implode("\n") }}</textarea></fieldset>
                <fieldset><legend>{{ __('mkt.top_countries') }}</legend>
                    <textarea name="countries" rows="8" placeholder="Polska: 92&#10;Niemcy: 3">{{ collect($aud['country'])->map(fn ($v, $k) => $k.': '.str_replace('.', ',', $v))->implode("\n") }}</textarea></fieldset>
                <button class="btn btn-dark btn-small">{{ __('ui.save') }}</button>
            </form>
            <form method="post" action="{{ route('social.campaigns.audience.import', $selected) }}" enctype="multipart/form-data" class="inline-form small" style="margin-top: .8rem">
                @csrf<span class="muted">{{ __('mkt.import_hint') }}</span><input type="file" name="csv" accept=".csv,text/csv,.txt" required><button class="btn btn-small">{{ __('ads.import') }}</button>
            </form>

        @else
            @php($pubs = $sel->publications()->sortByDesc('scheduled_at'))
            <div class="table-scroll"><table class="table">
                <thead><tr><th>{{ __('mkt.item') }}</th><th>{{ __('ui.date') }}</th><th>{{ __('mkt.reach') }}</th><th>{{ __('mkt.engagement') }}</th><th>{{ __('mkt.clicks') }}</th><th>{{ __('mkt.spend_col') }}</th></tr></thead>
                <tbody>
                @foreach($pubs as $p)
                    @php($m = $p->measurements->sortByDesc('measured_on')->first())
                    <tr><td><x-platform :platform="$p->platform" :size="16" /> <a href="{{ route('social.contents.edit', $p->content_id) }}">{{ \Illuminate\Support\Str::limit($p->content->title, 40) }}</a></td>
                        <td class="small">{{ $p->scheduled_at->format('d.m') }}</td><td>{{ $short($m->reach ?? 0) }}</td><td>{{ $m ? $m->reactions + $m->comments + $m->shares : 0 }}</td><td>{{ $m->clicks ?? 0 }}</td><td class="muted">—</td></tr>
                @endforeach
                @foreach($sel->ads() as $a)
                    @php($at = $a->totals())
                    <tr><td><x-icon name="zap" :size="14" /> <a href="{{ route('social.ads.edit', $a) }}">{{ \Illuminate\Support\Str::limit($a->name, 40) }}</a></td>
                        <td class="small">{{ $a->starts_on?->format('d.m') }}</td><td>{{ $short($at['reach']) }}</td><td class="muted">—</td><td>{{ $at['clicks'] }}</td><td>{{ $zl($at['spend']) }}</td></tr>
                @endforeach
                @if($pubs->isEmpty() && $sel->ads()->isEmpty())<tr><td colspan="6" class="muted">{{ __('mkt.no_data') }}</td></tr>@endif
                </tbody>
            </table></div>
        @endif
    </section>
    @endif
</div>

@if($selected)
@php($t = $selTotals)
@php($aud = $sel->audience())
<div class="mk-row3">
    <section class="card">
        <h2><x-icon name="filter" /> {{ __('mkt.funnel') }} <span class="mk-info" title="{{ __('mkt.funnel_hint') }}">ⓘ</span></h2>
        @php($fun = $sel->funnel($t))
        @if($fun->count() >= 2)
            <div class="mk-funnel">
                <div class="mk-funnel-steps">@foreach($fun as $i => $st)
                    <div class="mk-step s{{ $i }}" style="--w: {{ 100 - $i * 12 }}%"><strong>{{ __('mkt.step_'.$st->key) }}</strong><span>{{ $short($st->value) }} <small>({{ $pct($st->of_total) }}%)</small></span></div>
                @endforeach</div>
                <div class="mk-funnel-rates"><small class="muted">{{ __('mkt.conv_rate') }}</small>@foreach($fun->skip(1) as $st)<span>↓ {{ str_replace('.', ',', $st->step_rate) }}%</span>@endforeach</div>
            </div>
        @else<p class="muted small">{{ __('mkt.funnel_empty') }}</p>@endif
    </section>
    <section class="card">
        <h2><x-icon name="users" /> {{ __('mkt.demography') }} <span class="mk-info" title="{{ __('mkt.audience_hint') }}">ⓘ</span></h2>
        @if($aud['gender'] || $aud['age'])
            <div class="mk-gender">
                <div><span class="mk-g f">♀</span><span><small>{{ __('mkt.women') }}</small><strong>{{ str_replace('.', ',', round($aud['gender']['female'] ?? 0)) }}%</strong></span></div>
                <div><span class="mk-g m">♂</span><span><small>{{ __('mkt.men') }}</small><strong>{{ str_replace('.', ',', round($aud['gender']['male'] ?? 0)) }}%</strong></span></div>
            </div>
            <h3 class="small">{{ __('mkt.age_of') }}</h3>
            @php($maxAge = max([1, ...array_values($aud['age'])]))
            @foreach($aud['age'] as $label => $v)
                <div class="mk-age"><span>{{ $label }}</span><i><i style="width: {{ $v / $maxAge * 100 }}%"></i></i><b>{{ round($v) }}%</b></div>
            @endforeach
        @else<p class="muted small">{{ __('mkt.audience_empty') }} <a href="{{ route('social.campaigns.index', $q(['tab' => 'audience'])) }}#detail">{{ __('mkt.tab_audience') }} →</a></p>@endif
    </section>
    <section class="card">
        <h2><x-icon name="zap" /> {{ __('mkt.best_creatives') }} <span class="mk-info" title="{{ __('mkt.creatives_hint') }}">ⓘ</span></h2>
        @forelse($sel->creatives(3) as $cr)
            <a href="{{ $cr->url }}" class="mk-creative">@if($cr->thumb)<img src="{{ route('files.show', [$cr->thumb, $cr->thumb->name]) }}" alt="">@else<span class="mk-creative-empty"><x-icon name="image" :size="20" /></span>@endif
                <span><strong>{{ $cr->name }}</strong><small>CTR: <b>{{ str_replace('.', ',', $cr->ctr) }}%</b></small><small>{{ __('mkt.clicks') }}: <b>{{ number_format($cr->clicks, 0, ',', ' ') }}</b></small></span></a>
        @empty<p class="muted small">{{ __('mkt.no_creatives') }}</p>@endforelse
    </section>
</div>

<div class="mk-row2">
    <section class="card mk-loc">
        <h2><x-icon name="flag" /> {{ __('mkt.locations') }}</h2>
        <div class="mk-loc-body">
            <div>
                <div class="pv-modes mk-loc-tabs" role="tablist"><button type="button" class="on" data-loc="city">{{ __('mkt.top_cities') }}</button><button type="button" data-loc="country">{{ __('mkt.top_countries') }}</button></div>
                @foreach(['city', 'country'] as $dim)
                    <table class="mk-loc-list" data-loc-list="{{ $dim }}" @if($dim === 'country') hidden @endif>
                        @forelse(array_slice($aud[$dim], 0, 6, true) as $label => $v)<tr><td>{{ $label }}</td><td>{{ str_replace('.', ',', $v) }}%</td></tr>
                        @empty<tr><td class="muted small">{{ __('mkt.audience_empty') }}</td></tr>@endforelse
                    </table>
                @endforeach
            </div>
            @include('social.campaigns._map', ['cities' => $aud['city']])
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2><x-icon name="bar-chart" /> {{ __('mkt.top_campaigns') }} <span class="mk-info" title="{{ __('mkt.top_hint') }}">ⓘ</span></h2></div>
        <div class="table-scroll"><table class="table mk-rank">
            <thead><tr><th>#</th><th>{{ __('mkt.campaign') }}</th><th>{{ __('mkt.channel') }}</th><th>{{ __('mkt.budget') }}</th><th>{{ __('mkt.clicks') }}</th><th>{{ __('mkt.conversions') }}</th><th>ROAS</th><th>{{ __('mkt.status') }}</th></tr></thead>
            <tbody>@foreach($ranking->take(8) as $n => $c)
                @php($rt = $totals[$c->id])
                @php($cv = $insights[$c->id]->cover())
                <tr><td>{{ $n + 1 }}</td>
                    <td><a href="{{ route('social.campaigns.index', $q(['c' => $c->id])) }}#detail" class="mk-rank-name">@if($cv)<img src="{{ route('files.show', [$cv, $cv->name]) }}" alt="">@else<span class="mk-creative-empty sm"><x-icon name="layers" :size="14" /></span>@endif<span>{{ $c->name }}@if($withClient($c))<small class="muted">– {{ $c->client->name }}</small>@endif</span></a></td>
                    <td><span class="mk-card-icons">@foreach($insights[$c->id]->platformKeys()->take(3) as $pk)<x-platform :platform="$pk" :size="18" />@endforeach</span></td>
                    <td class="nowrap">{{ number_format((float) $c->budget, 0, ",", " ") }} zł</td><td>{{ number_format($rt['clicks'], 0, ',', ' ') }}</td><td>{{ number_format($rt['conversions'], 0, ',', ' ') }}</td>
                    <td>{{ $rt['roas'] !== null ? str_replace('.', ',', $rt['roas']).' ×' : '—' }}</td>
                    <td><span class="mk-status mk-{{ $c->status }}">{{ __('mkt.status_'.$c->status) }}</span></td></tr>
            @endforeach</tbody>
        </table></div>
    </section>
</div>
@endif
@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-loc]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-loc]').forEach(x => x.classList.toggle('on', x === b));
    document.querySelectorAll('[data-loc-list]').forEach(t => t.hidden = t.dataset.locList !== b.dataset.loc);
}));
</script>
@endpush
