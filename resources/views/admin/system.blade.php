@extends('layouts.app')
@section('title', __('ui.system_log'))
@section('content')
<h1 class="page-title">{{ __('ui.system_log') }}</h1>
<div class="cards">
    <div class="card stat"><strong>{{ $lastCron ? \Illuminate\Support\Carbon::parse($lastCron)->diffForHumans() : '—' }}</strong><span>{{ __('ui.last_cron') }}</span></div>
    <div class="card stat"><strong>{{ $pendingJobs }}</strong><span>{{ __('ui.pending_jobs') }}</span></div>
    <div class="card stat {{ $failedJobs ? 'warn' : '' }}"><strong>{{ $failedJobs }}</strong><span>{{ __('ui.failed_jobs') }}</span></div>
</div>
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
