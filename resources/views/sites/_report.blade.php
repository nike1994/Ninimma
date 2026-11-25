{{-- Raport miesięczny (administrator i klient). Bez notatek roboczych i kosztów linków. --}}
<div class="report-grid">
    <section class="card card-dark">
        <x-waves variant="card" />
        <h2>{{ __('site.package_usage') }}</h2>
        @if($report['package'])
            @php($pct = min(100, (int) round($report['minutes'] / $report['package'] * 100)))
            <p class="dark-title">{{ \App\Models\TimeEntry::format($report['minutes']) }} <small>/ {{ \App\Models\TimeEntry::format($report['package']) }} h</small></p>
            <div class="limit-bar dark"><span style="width: {{ $pct }}%"></span></div>
            <small>{{ __('site.pct_used', ['pct' => $pct]) }}</small>
        @else
            <p class="dark-title">{{ \App\Models\TimeEntry::format($report['minutes']) }} h</p>
            <small>{{ __('site.no_package') }}</small>
        @endif
    </section>
    <section class="card">
        <h2><x-icon name="check-circle" /> {{ __('site.done_this_month') }}</h2>
        @forelse($report['logs'] as $l)
            <div class="log-row"><span class="log-kind kind-{{ $l->kind }}">{{ __('site.log_'.$l->kind) }}</span><span><small class="muted">{{ $l->logged_on->format('d.m') }}</small> {!! nl2br(e($l->description)) !!}</span></div>
        @empty
            <p class="muted small">{{ __('ui.nothing_here') }}</p>
        @endforelse
    </section>
    <section class="card">
        <h2><x-icon name="search" /> {{ __('site.positions') }}</h2>
        <table class="table">
            <thead><tr><th>{{ __('site.phrase') }}</th><th>{{ __('site.position') }}</th><th>{{ __('site.change') }}</th></tr></thead>
            <tbody>
            @forelse($report['phrases'] as $r)
                <tr><td>{{ $r->phrase->phrase }}</td><td><strong>{{ $r->position ?? '—' }}</strong></td>
                    <td>@if($r->change !== null)<span @class(['trend-pill sm', 'down' => $r->change < 0])>{{ $r->change > 0 ? '↑ +'.$r->change : ($r->change < 0 ? '↓ '.$r->change : '=') }}</span>@else — @endif</td></tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('ui.nothing_here') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2><x-icon name="calendar" /> {{ __('site.planned_next') }}</h2>
        @forelse($report['planned'] as $c)
            <div class="deadline"><span class="date-tile"><strong>{{ $c->due_on->format('j') }}</strong><small>{{ $c->due_on->translatedFormat('M') }}</small></span><span><strong>{{ $c->working_title }}</strong></span></div>
        @empty
            <p class="muted small">{{ __('ui.nothing_here') }}</p>
        @endforelse
    </section>
</div>
