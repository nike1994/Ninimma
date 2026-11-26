@extends('layouts.app')
@section('title', __('ui.system_log'))
@section('content')
<h1 class="page-title">{{ __('ui.system_log') }}</h1>
<div class="cards">
    <div class="card stat"><strong>{{ $lastCron ? \Illuminate\Support\Carbon::parse($lastCron)->diffForHumans() : '—' }}</strong><span>{{ __('ui.last_cron') }}</span></div>
    <div class="card stat"><strong>{{ $pendingJobs }}</strong><span>{{ __('ui.pending_jobs') }}</span></div>
    <div class="card stat {{ $failedJobs ? 'warn' : '' }}"><strong>{{ $failedJobs }}</strong><span>{{ __('ui.failed_jobs') }}</span></div>
</div>
<section class="card inbound-card">
    <h2><x-icon name="mail" /> {{ __('mail.inbound_title') }}</h2>
    @if($inbound['enabled'])
        <p class="small">{{ __('mail.inbound_address') }}: <code>{{ $inbound['address'] }}</code> · {{ __('mail.inbound_mode_'.$inbound['mode']) }}
            @if($inbound['mode'] === 'imap') · {{ __('mail.inbound_last') }}: {{ $inbound['last'] ? \Illuminate\Support\Carbon::parse($inbound['last'])->diffForHumans() : '—' }}@endif</p>
        @if($inbound['error'])<p class="flash flash-error small">{{ $inbound['error'] }}</p>@endif
        <div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('ui.date') }}</th><th>{{ __('mail.inbound_from') }}</th><th>{{ __('mail.inbound_subject') }}</th><th>{{ __('mail.inbound_result') }}</th></tr></thead>
            <tbody>@forelse($inbound['recent'] as $r)
                <tr><td class="small">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d.m H:i') }}</td><td class="small">{{ $r->from_email }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($r->subject, 60) }}</td>
                    <td class="small"><span @class(['pill', 'pill-done' => str_starts_with($r->status, 'accepted'), 'pill-pink' => str_starts_with($r->status, 'rejected') || $r->status === 'error'])>{{ __('mail.inbound_status_'.$r->status) }}</span>
                        @if($r->ticket_id) <a href="{{ route('tickets.show', $r->ticket_id) }}">#{{ $r->ticket_id }}</a>@endif
                        @if($r->attachments) · 📎 {{ $r->attachments }}@endif</td></tr>
            @empty<tr><td colspan="4" class="muted">{{ __('ui.nothing_here') }}</td></tr>@endforelse</tbody>
        </table></div>
    @else
        <p class="muted small">{{ __('mail.inbound_disabled') }}</p>
    @endif
</section>
<form class="filters">
    <select name="type" onchange="this.form.submit()">
        <option value="">{{ __('ui.all_types') }}</option>
        @foreach($types as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach
    </select>
</form>
<section class="card">
    <table class="table">
        <thead><tr><th>{{ __('ui.date') }}</th><th>{{ __('ui.type') }}</th><th>{{ __('ui.message') }}</th><th>{{ __('ui.details') }}</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr class="level-{{ $log->level }}">
                <td class="small">{{ $log->created_at->format('Y-m-d H:i') }}</td><td>{{ $log->type }}</td><td>{{ $log->message }}</td>
                <td class="small muted"><code>{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">{{ __('ui.nothing_here') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<x-pager :paginator="$logs" />
@endsection
