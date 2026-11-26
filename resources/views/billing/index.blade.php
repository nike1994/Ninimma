@extends('layouts.app')
@section('title', __('ui.billing'))
@section('content')
@php($me = auth()->user())
<div class="page-head"><h1 class="page-title">{{ __('ui.billing') }}</h1></div>
<div class="grid-main">
    <div>
        <section class="card">
            @if($me->isAdmin())
            <form class="filters">
                <select name="client" onchange="this.form.submit()"><option value="">{{ __('ui.all_clients') }}</option>
                    @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                <label class="check"><input type="checkbox" name="state" value="unpaid" @checked(request('state') === 'unpaid') onchange="this.form.submit()"> {{ __('ui.only_unpaid') }}</label>
            </form>
            @endif
            <div class="table-scroll"><table class="table">
                <thead><tr><th>{{ __('ui.doc_number') }}</th>@if($me->isAdmin())<th>{{ __('ui.client') }}</th>@endif<th>{{ __('ui.ticket') }}</th><th>{{ __('ui.issued_on') }}</th><th>{{ __('ui.amount') }}</th><th>{{ __('ui.payment') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($docs as $d)
                    <tr>
                        <td><strong>{{ $d->number }}</strong></td>
                        @if($me->isAdmin())<td>{{ $d->client->name }}</td>@endif
                        <td>@if($d->ticket)<a href="{{ route('tickets.show', $d->ticket) }}">#{{ $d->ticket->id }}</a>@else — @endif</td>
                        <td>{{ $d->issued_on->format('d.m.Y') }}@if($d->due_on)<small class="muted d-block">{{ __('ui.due_on') }}: {{ $d->due_on->format('d.m.Y') }}</small>@endif</td>
                        <td><strong>{{ number_format((float) $d->amount, 2, ',', ' ') }} zł</strong></td>
                        <td>
                            @if($me->isAdmin())
                                <form method="post" action="{{ route('admin.cost-documents.paid', $d) }}">@csrf @method('patch')
                                    <button @class(['pill', 'pill-done' => $d->is_paid, 'pill-pink' => $d->isOverdue(), 'pill-needs_decision' => ! $d->is_paid && ! $d->isOverdue()])>{{ $d->is_paid ? __('ui.paid') : ($d->isOverdue() ? __('ui.overdue') : __('ui.unpaid')) }}</button></form>
                            @else
                                <span @class(['pill', 'pill-done' => $d->is_paid, 'pill-pink' => $d->isOverdue(), 'pill-needs_decision' => ! $d->is_paid && ! $d->isOverdue()])>{{ $d->is_paid ? __('ui.paid') : ($d->isOverdue() ? __('ui.overdue') : __('ui.unpaid')) }}</span>
                            @endif
                        </td>
                        <td>@if($d->pdf)<a href="{{ route('files.show', [$d->pdf, $d->pdf->name]) }}" class="icon-btn icon-btn-sm" title="PDF"><x-icon name="download" :size="16" /></a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('ui.nothing_here') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <x-pager :paginator="$docs" />
        </section>
        <section class="card">
            <h2>{{ __('ui.accepted_quotes') }}</h2>
            @forelse($quotes as $q)
                <a href="{{ route('tickets.show', $q->ticket) }}" class="side-file"><span>#{{ $q->ticket->id }} {{ $q->ticket->title }} · v{{ $q->version }}</span>
                    <small class="muted">@if($q->acceptedMinutes()){{ \App\Models\TimeEntry::hours($q->acceptedMinutes()) }}@endif @if($q->acceptedAmount()) · {{ number_format($q->acceptedAmount(), 0, ',', ' ') }} zł @endif</small></a>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </section>
    </div>
    <aside>
        <section class="card card-dark"><x-waves variant="card" />
            <h2>{{ __('ui.to_pay') }}</h2>
            <p class="dark-title">{{ number_format((float) $unpaidTotal, 2, ',', ' ') }} zł</p>
            <small>{{ __('ui.ksef_hint') }}</small>
        </section>
        @if($me->isAdmin())
        <form method="post" action="{{ route('admin.cost-documents.store') }}" class="card stack" data-message-form>
            @csrf
            <h2><x-icon name="plus" /> {{ __('ui.add_document') }}</h2>
            <label class="field"><span>{{ __('ui.client') }}</span><select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
            <x-field name="ticket_id" type="number" :label="__('ui.ticket_number_optional')" />
            <x-field name="number" :label="__('ui.doc_number')" required />
            <x-field name="amount" type="number" step="0.01" :label="__('ui.amount_gross')" required />
            <div class="form-row form-row-2"><x-field name="issued_on" type="date" :label="__('ui.issued_on')" :value="today()->toDateString()" required /><x-field name="due_on" type="date" :label="__('ui.due_on')" /></div>
            <label class="check"><input type="checkbox" name="is_paid" value="1"> {{ __('ui.paid') }}</label>
            @include('tickets._uploader', ['noSend' => true])
            <button class="btn btn-dark">{{ __('ui.save') }}</button>
        </form>
        @endif
    </aside>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/uploader.js') }}?v=3"></script>@endpush
