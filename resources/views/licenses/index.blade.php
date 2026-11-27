@extends('layouts.app')
@section('title', __('lic.title'))
@section('content')
@php
    $pos = fn ($date) => max(0, min(100, $axisStart->diffInDays($date, false) / $axisDays * 100));
    $todayPos = $pos(today());
    $months = collect();
    for ($d = $axisStart->copy()->startOfMonth()->addMonth(); $d->lte($axisEnd); $d->addMonth()) { $months->push($d->copy()); }
@endphp
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="shield" :size="26" /></span>
        <div><h1>{{ __('lic.title') }}</h1><p class="muted">{{ __('lic.subtitle') }}</p></div></div>
    <div class="dash-head-right">
        <form method="get" class="inline-form">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('lic.search') }}">
            <select name="client" onchange="this.form.submit()"><option value="">{{ __('ui.all_clients') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        </form>
    </div>
</div>

<div class="kpis kpis-4">
    <a href="{{ route('licenses.index', ['filter' => 'active']) }}" class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="check-circle" :size="24" /></span><div><span class="kpi-label">{{ __('lic.kpi_active') }}</span><div class="kpi-value"><strong>{{ $counts['active'] }}</strong></div></div></div><x-waves variant="kpi" /></a>
    <a href="{{ route('licenses.index', ['filter' => 'expiring']) }}" class="kpi kpi-warn"><div class="kpi-row"><span class="kpi-icon"><x-icon name="clock" :size="24" /></span><div><span class="kpi-label">{{ __('lic.kpi_expiring') }}</span><div class="kpi-value"><strong>{{ $counts['expiring'] }}</strong></div><small class="muted">{{ __('lic.kpi_expiring_hint') }}</small></div></div><x-waves variant="kpi" /></a>
    <a href="{{ route('licenses.index', ['filter' => 'expired']) }}" class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="lock" :size="24" /></span><div><span class="kpi-label">{{ __('lic.kpi_expired') }}</span><div class="kpi-value"><strong>{{ $counts['expired'] }}</strong></div></div></div><x-waves variant="kpi" /></a>
    <a href="{{ route('licenses.index', ['filter' => 'conflicts']) }}" class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="alert" :size="24" /></span><div><span class="kpi-label">{{ __('lic.kpi_conflicts') }}</span><div class="kpi-value"><strong>{{ $counts['conflicts'] }}</strong></div><small>{{ __('lic.kpi_conflicts_hint') }}</small></div></div><x-waves variant="card" /></a>
</div>

@if($issues->isNotEmpty())
    <section class="card lic-conflicts">
        <h2><x-icon name="alert" /> {{ __('lic.conflicts') }} <span class="pill pill-pink">{{ $issues->count() }}</span></h2>
        <p class="muted small">{{ __('lic.conflicts_hint') }}</p>
        @include('licenses._issues', ['issues' => $issues->take(12)])
    </section>
@endif

<div class="chips" style="margin: 1rem 0">
    @foreach(['all', 'expiring', 'active', 'expired', 'conflicts', 'none'] as $f)
        <a href="{{ route('licenses.index', array_filter(['filter' => $f, 'client' => request('client'), 'q' => request('q')])) }}" @class(['chip', 'chip-dark' => $filter === $f])>{{ __('lic.filter_'.$f) }}</a>
    @endforeach
</div>

<section class="card lic-board">
    <div class="lic-axis" aria-hidden="true">
        <span class="lic-axis-pad"></span>
        <span class="lic-axis-track">
            @foreach($months as $mo)<span class="lic-axis-month" style="left: {{ $pos($mo) }}%">{{ $mo->translatedFormat('M') }}</span>@endforeach
            <span class="lic-axis-today" style="left: {{ $todayPos }}%">{{ __('lic.today') }}</span>
        </span>
    </div>
    @forelse($materials as $m)
        @php
            $issuesM = $issues->where('material.id', $m->id);
            $start = $m->license_from ?? $m->created_at->copy()->startOfDay();
            $pubCount = $m->contents->flatMap->publications->where('status', '!=', 'cancelled')->count() + ($m->publication_id ? 1 : 0);
        @endphp
        <article class="lic-row lic-{{ $m->lic->state }}" id="m{{ $m->id }}">
            <div class="lic-main">
                <span class="lic-thumb-wrap"><x-material-thumb :material="$m" :size="64" :player="true" /><x-to-board :usage="$m->file" :compact="true" class="to-board-float" /></span>
                <x-license-ring :material="$m" :size="58" />
                <div class="lic-info">
                    <strong>{{ $m->description }}</strong>
                    <small class="muted">{{ $m->collaboration->influencer->name }} · {{ $m->collaboration->campaign->client->name }} · <a href="{{ route('collaborations.show', $m->collaboration) }}">{{ $m->collaboration->campaign->name }}</a></small>
                    <small class="lic-channels">@foreach(\App\Models\Material::CHANNELS as $ch)<span @class(['lic-ch', 'off' => ! $m->allows($ch)])>{{ __('lic.ch_'.$ch) }}</span>@endforeach
                        @if($m->requires_takedown)<span class="lic-ch lic-ch-warn">{{ __('lic.takedown_short') }}</span>@endif</small>
                </div>
            </div>
            <div class="lic-track" title="{{ $m->license_until ? $start->format('d.m.Y').' – '.$m->license_until->format('d.m.Y') : __('lic.state_none') }}">
                <span class="lic-today" style="left: {{ $todayPos }}%"></span>
                @if($m->license_until)
                    @php($l = $pos($start)) @php($r = $pos($m->license_until))
                    <span @class(['lic-bar', 'lic-bar-short' => $r - $l < 22, 'lic-bar-flip' => $r - $l < 22 && $r > 78]) style="left: {{ $l }}%; width: {{ max(1.2, $r - $l) }}%">
                        <span>{{ $start->format('d.m') }} – {{ $m->license_until->format('d.m.y') }}</span>
                    </span>
                    @foreach($m->contents->flatMap->publications->where('status', '!=', 'cancelled') as $p)
                        @if($p->scheduled_at->between($axisStart, $axisEnd))<span class="lic-dot {{ $p->status }}" style="left: {{ $pos($p->scheduled_at) }}%" title="{{ $p->platform->label() }} {{ $p->scheduled_at->format('d.m H:i') }}"></span>@endif
                    @endforeach
                    @foreach($m->ads->whereIn('status', ['planned', 'active', 'paused']) as $ad)
                        @if($ad->starts_on)@php($al = $pos($ad->starts_on)) @php($ar = $ad->ends_on ? $pos($ad->ends_on) : 100)<span class="lic-ad" style="left: {{ $al }}%; width: {{ max(1, $ar - $al) }}%" title="{{ $ad->name }}"></span>@endif
                    @endforeach
                @else
                    <span class="lic-bar lic-bar-none"><span>{{ __('lic.no_dates') }}</span></span>
                @endif
            </div>
            <div class="lic-side">
                <small class="muted">{{ trans_choice('lic.used_posts', $pubCount, ['n' => $pubCount]) }} · {{ trans_choice('lic.used_ads', $m->ads->count(), ['n' => $m->ads->count()]) }}</small>
                @if($m->file && $m->usableForPost())<a href="{{ route('social.contents.create', ['material' => $m->id]) }}" class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /> {{ __('lic.create_post') }}</a>@endif
                @if($issuesM->isNotEmpty())<span class="pill pill-pink">{{ trans_choice('lic.n_conflicts', $issuesM->count(), ['n' => $issuesM->count()]) }}</span>@endif
                <details class="lic-pop">
                    <summary class="btn btn-small">{{ __('lic.manage') }}</summary>
                    <div class="card stack">
                        @include('licenses._issues', ['issues' => $issuesM])
                        <form method="post" action="{{ route('licenses.extend', $m) }}" class="stack lic-extend">
                            @csrf
                            <h3><x-icon name="repeat" :size="16" /> {{ __('lic.extend') }}</h3>
                            <div class="chips">
                                @foreach([1, 3, 6, 12] as $mo)
                                    @php($base = $m->license_until && $m->license_until->isFuture() ? $m->license_until : today())
                                    <button type="button" class="chip" data-set-date="{{ $base->copy()->addMonths($mo)->toDateString() }}">+{{ $mo }} {{ __('lic.months_short') }}</button>
                                @endforeach
                            </div>
                            <div class="form-row form-row-2">
                                <label class="field"><span>{{ __('lic.new_until') }}</span><input type="date" name="new_until" required min="{{ today()->addDay()->toDateString() }}"></label>
                                <label class="field"><span>{{ __('lic.cost') }}</span><input type="number" name="cost" step="0.01" min="0" placeholder="0,00"></label>
                            </div>
                            <input name="note" placeholder="{{ __('lic.note_placeholder') }}" maxlength="255">
                            <button class="btn btn-dark btn-small">{{ __('lic.extend') }}</button>
                        </form>
                        <form method="post" action="{{ route('licenses.update', $m) }}" class="stack">
                            @csrf @method('put')
                            <h3><x-icon name="sliders" :size="16" /> {{ __('lic.terms') }}</h3>
                            <div class="form-row form-row-2">
                                <label class="field"><span>{{ __('lic.from') }}</span><input type="date" name="license_from" value="{{ $m->license_from?->toDateString() }}"></label>
                                <label class="field"><span>{{ __('lic.until') }}</span><input type="date" name="license_until" value="{{ $m->license_until?->toDateString() }}"></label>
                            </div>
                            @include('licenses._channels', ['selected' => $m->license_channels])
                            <label class="check"><input type="checkbox" name="requires_takedown" value="1" @checked($m->requires_takedown)> {{ __('lic.requires_takedown') }}</label>
                            <label class="check"><input type="checkbox" name="client_download" value="1" @checked($m->client_download)> {{ __('files.client_download_label') }}</label>
                            <button class="btn btn-small">{{ __('ui.save') }}</button>
                        </form>
                        @if($m->file)
                            <h3><x-icon name="eye" :size="16" /> {{ __('files.access_log') }}</h3>
                            <ul class="access-log">
                                @forelse($access[$m->file_usage_id] ?? [] as $a)
                                    <li @class(['blocked' => $a->action === 'blocked'])>{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d.m H:i') }} · {{ $a->name }} · {{ __('files.access_'.$a->action) }}</li>
                                @empty<li class="muted">{{ __('files.no_access') }}</li>@endforelse
                            </ul>
                        @endif
                        @if($m->extensions->isNotEmpty())
                            <h3><x-icon name="history" :size="16" /> {{ __('lic.history') }}</h3>
                            <ul class="lic-history">@foreach($m->extensions as $e)<li><strong>{{ $e->previous_until?->format('d.m.Y') ?? '—' }} → {{ $e->new_until->format('d.m.Y') }}</strong>@if($e->cost) · {{ number_format($e->cost, 2, ',', ' ') }} zł @endif<small class="muted"> · {{ $e->user?->name }}, {{ $e->created_at->format('d.m.Y') }}@if($e->note) · {{ $e->note }}@endif</small></li>@endforeach</ul>
                        @endif
                    </div>
                </details>
            </div>
        </article>
    @empty
        <p class="muted">{{ __('lic.empty') }}</p>
    @endforelse
</section>
<p class="muted small lic-legend"><span class="lic-dot planned"></span> {{ __('lic.legend_post') }} <span class="lic-dot published"></span> {{ __('lic.legend_published') }} <span class="lic-ad lic-ad-legend"></span> {{ __('lic.legend_ad') }}</p>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-set-date]').forEach(b => b.addEventListener('click', () => {
    b.closest('form').querySelector('[name=new_until]').value = b.dataset.setDate;
}));
</script>
@endpush
