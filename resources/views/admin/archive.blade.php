@extends('layouts.app')
@section('title', __('arch.title'))
@section('content')
@php($mb = fn ($b) => number_format($b / 1048576, 1, ',', ' ').' MB')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="download" :size="26" /></span>
        <div><h1>{{ __('arch.title') }}</h1><p class="muted">{{ __('arch.subtitle') }}</p></div></div>
</div>
<div class="kpis kpis-4">
    <div class="kpi kpi-dark"><div class="kpi-row"><span class="kpi-icon"><x-icon name="layers" :size="24" /></span><div><span class="kpi-label">{{ __('arch.on_hosting') }}</span><div class="kpi-value"><strong>{{ $mb($total) }}</strong></div></div></div><x-waves variant="card" /></div>
    <div class="kpi {{ $threshold && $total > $threshold ? 'stat-pink' : '' }}"><div class="kpi-row"><span class="kpi-icon"><x-icon name="alert" :size="24" /></span><div><span class="kpi-label">{{ __('arch.threshold') }}</span><div class="kpi-value"><strong>{{ $threshold ? $mb($threshold) : '—' }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="clock" :size="24" /></span><div><span class="kpi-label">{{ __('arch.ready_count') }}</span><div class="kpi-value"><strong>{{ $ready->count() }}</strong></div></div></div><x-waves variant="kpi" /></div>
    <div class="kpi"><div class="kpi-row"><span class="kpi-icon"><x-icon name="upload" :size="24" /></span><div><span class="kpi-label">{{ __('arch.offline_count') }}</span><div class="kpi-value"><strong>{{ $offline->count() }}</strong></div></div></div><x-waves variant="kpi" /></div>
</div>
@if($threshold && $total > $threshold)<div class="flash flash-error" style="margin: 0 0 1rem">{{ __('arch.over_threshold') }}</div>@endif
<div class="grid-main">
    <div>
        <section class="card">
            <div class="card-head"><h2>{{ __('arch.ready') }}</h2>
                @if($ready->isNotEmpty())<form method="post" action="{{ route('admin.archive.ready') }}">@csrf<button class="btn btn-small btn-dark">{{ __('arch.archive_all') }}</button></form>@endif</div>
            <p class="muted small">{{ __('arch.ready_hint') }}</p>
            @forelse($ready as $t)
                <div class="side-file"><a href="{{ route('tickets.show', $t) }}"><span>#{{ $t->id }} {{ $t->title }}</span></a><small class="muted">{{ $t->client->name }} · {{ $t->closed_at->format('d.m.Y') }}</small>
                    <form method="post" action="{{ route('admin.tickets.archive', $t) }}">@csrf<button class="btn btn-small">{{ __('arch.archive') }}</button></form></div>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </section>
        <form method="post" action="{{ route('admin.archive.offline') }}" class="card">
            @csrf
            <div class="card-head"><h2>{{ __('arch.archived') }}</h2><button class="btn btn-small btn-dark">{{ __('arch.move_offline') }}</button></div>
            <p class="muted small">{{ __('arch.offline_hint') }}</p>
            @forelse($archived as $t)
                <label class="side-file"><input type="checkbox" name="tickets[]" value="{{ $t->id }}"><span><a href="{{ route('tickets.show', $t) }}">#{{ $t->id }} {{ $t->title }}</a></span><small class="muted">{{ $t->client->name }} · {{ $t->closed_at?->format('d.m.Y') }}</small></label>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </form>
        <section class="card">
            <h2>{{ __('arch.offline') }}</h2>
            @forelse($offline as $t)
                <div class="side-file"><span><a href="{{ route('tickets.show', $t) }}">#{{ $t->id }} {{ $t->title }}</a></span><small class="muted">📦 {{ $t->offline_package }}</small></div>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
            <form method="post" action="{{ route('admin.archive.restore-package') }}" enctype="multipart/form-data" class="inline-form" style="margin-top: .8rem">
                @csrf<span class="muted small">{{ __('arch.restore_hint') }}</span><input type="file" name="package" accept=".zip" required><button class="btn btn-small">{{ __('arch.restore_package') }}</button>
            </form>
        </section>
        <section class="card">
            <h2>{{ __('arch.packages') }}</h2>
            <table class="table"><thead><tr><th>{{ __('arch.package_name') }}</th><th>{{ __('ui.type') }}</th><th>{{ __('arch.size') }}</th><th>{{ __('ui.date') }}</th><th></th></tr></thead><tbody>
                @forelse($packages as $p)
                    <tr><td class="small"><code>{{ $p->name }}</code></td><td>{{ __('arch.kind_'.$p->kind) }}</td><td>{{ $mb($p->size) }}</td><td class="small">{{ $p->created_at->format('d.m.Y H:i') }}</td>
                        <td class="actions"><a href="{{ route('admin.packages.download', $p) }}" class="icon-btn icon-btn-sm"><x-icon name="download" :size="16" /></a>
                            <form method="post" action="{{ route('admin.packages.destroy', $p) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('delete')<button class="icon-btn icon-btn-sm"><x-icon name="x" :size="16" /></button></form></td></tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('ui.nothing_here') }}</td></tr>
                @endforelse
            </tbody></table>
            <p class="muted small">{{ __('arch.homelab_hint') }}</p>
        </section>
    </div>
    <aside>
        <section class="card">
            <h2>{{ __('arch.usage') }}</h2>
            @foreach($usage as $u)
                <a href="{{ route('admin.clients.show', $u->client) }}" class="side-file"><x-avatar :name="$u->client->name" :size="28" /><span>{{ $u->client->name }}<small class="muted d-block">{{ trans_choice('arch.tickets_n', $u->tickets, ['n' => $u->tickets]) }}@if($u->offline) · offline {{ $mb($u->offline) }}@endif</small></span><strong class="small">{{ $mb($u->bytes) }}</strong></a>
            @endforeach
        </section>
        <form method="post" action="{{ route('admin.archive.settings') }}" class="card stack">
            @csrf @method('put')
            <h2><x-icon name="sliders" /> {{ __('ui.settings') }}</h2>
            <x-field name="offline_threshold_mb" type="number" min="0" :label="__('arch.threshold_mb')" :value="$threshold / 1048576 ?: ''" />
            <x-field name="default_retention_months" type="number" min="0" :label="__('arch.default_retention')" :value="$retention ?: ''" />
            <x-field name="hourly_rate" type="number" step="0.01" min="0" :label="__('arch.hourly_rate')" :value="\App\Models\Setting::get('hourly_rate', 150)" />
            <p class="muted small">{{ __('arch.retention_hint') }}</p>
            <button class="btn btn-dark">{{ __('ui.save') }}</button>
        </form>
    </aside>
</div>
@endsection
