@extends('layouts.app')
@section('title', $c->influencer->name.' · '.$c->campaign->name)
@section('content')
<header class="client-head">
    @include('influencers._photo', ['inf' => $c->influencer, 'size' => 72])
    <div class="client-id">
        <h1><a href="{{ route('influencers.show', $c->influencer) }}">{{ $c->influencer->name }}</a></h1>
        <p class="muted">{{ __('inf.campaign') }}: <a href="{{ route('social.campaigns.edit', $c->campaign) }}">{{ $c->campaign->name }}</a> · {{ $c->campaign->client->name }}</p>
    </div>
    <div class="client-badges">
        <span @class(['pill pill-lg', 'pill-done' => $c->status === 'done', 'pill-pink' => $c->status === 'cancelled', 'pill-with_agency' => $c->status === 'active'])>{{ __('inf.stage_'.$c->stage()) }}</span>
    </div>
</header>

@if($c->status === 'cancelled')<div class="flash flash-error" style="margin: 0 0 1rem">{{ __('inf.cancelled_reason', ['reason' => $c->cancel_reason]) }}</div>@endif

<section class="card">
    <h2>{{ __('inf.stages') }}</h2>
    <div class="steps-6">
        @php
            $steps = [
                'contract' => [['contract_sent', __('inf.step_contract_sent')], ['contract_signed', __('inf.step_contract_signed')]],
                'brief' => [['brief_sent', __('inf.step_brief_sent')], ['brief_accepted', __('inf.step_brief_accepted')]],
                'shipment' => [],
                'materials' => [['materials_complete', __('inf.step_materials_complete')]],
                'publication' => [['published', __('inf.step_published')]],
                'settlement' => [['paid', __('inf.step_paid')]],
            ];
            $idx = $c->stageIndex();
        @endphp
        @foreach(\App\Models\Collaboration::STAGES as $i => $st)
            <div @class(['step6', 'done' => $i < $idx, 'now' => $i === $idx, 'skip' => $st === 'shipment' && ! $c->needs_shipment])>
                <span class="step6-num">@if($i < $idx)<x-icon name="check" :size="16" />@else{{ $i + 1 }}@endif</span>
                <strong>{{ __('inf.stage_'.$st) }}</strong>
                @foreach($steps[$st] as [$field, $label])
                    <form method="post" action="{{ route('collaborations.step', $c) }}">@csrf<input type="hidden" name="step" value="{{ $field }}">
                        <button @class(['step-check', 'on' => $c->{$field.'_at'}])><span class="todo-box">@if($c->{$field.'_at'})<x-icon name="check" :size="12" />@endif</span>{{ $label }}
                            @if($c->{$field.'_at'})<small class="muted">{{ $c->{$field.'_at'}->format('d.m') }}</small>@endif</button></form>
                @endforeach
                @if($st === 'shipment')
                    <small class="muted">{{ $c->needs_shipment ? ($c->shipmentsDelivered() ? __('inf.delivered') : __('inf.see_shipments')) : __('inf.no_shipment') }}</small>
                @endif
            </div>
        @endforeach
    </div>
</section>

<div class="grid-main">
    <div>
        <section class="card">
            <h2><x-icon name="upload" /> {{ __('inf.shipments') }}</h2>
            @foreach($c->shipments as $s)
                <div class="ship-row">
                    <span><strong>{{ $s->contents }}</strong> × {{ $s->quantity }}<small class="muted d-block">@if($s->tracking_number){{ __('inf.tracking') }}: {{ $s->tracking_number }} · @endif @if($s->value){{ number_format((float) $s->value, 0, ',', ' ') }} zł @endif</small></span>
                    @foreach(['sent_on' => __('inf.sent'), 'delivered_on' => __('inf.arrived')] as $f => $label)
                        <form method="post" action="{{ route('shipments.update', $s) }}">@csrf @method('patch')<input type="hidden" name="field" value="{{ $f }}">
                            <button @class(['step-check', 'on' => $s->{$f}])><span class="todo-box">@if($s->{$f})<x-icon name="check" :size="12" />@endif</span>{{ $label }}@if($s->{$f}) <small class="muted">{{ $s->{$f}->format('d.m') }}</small>@endif</button></form>
                    @endforeach
                </div>
            @endforeach
            <form method="post" action="{{ route('collaborations.shipments.store', $c) }}" class="ship-add">
                @csrf
                <input name="contents" placeholder="{{ __('inf.what') }}" required>
                <input name="quantity" type="number" min="1" value="1" required>
                <input name="value" type="number" step="0.01" min="0" placeholder="{{ __('inf.value') }}">
                <input name="tracking_number" placeholder="{{ __('inf.tracking') }}">
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /></button>
            </form>
        </section>

        <section class="card">
            <h2><x-icon name="image" /> {{ __('inf.materials') }}</h2>
            <p class="muted small">{{ __('inf.materials_hint') }}</p>
            @foreach($c->materials as $m)
                <form method="post" action="{{ route('materials.update', $m) }}" class="material-row" data-message-form>
                    @csrf @method('put')
                    <span class="material-thumb-lic"><x-material-thumb :material="$m" :size="76" :avatar="false" />
                        <x-license-ring :material="$m" :size="40" /></span>
                    <span class="material-body">
                        <strong>{{ $m->description }}</strong>
                        <small class="lic-channels">@foreach(\App\Models\Material::CHANNELS as $ch)<span @class(['lic-ch', 'off' => ! $m->allows($ch)])>{{ __('lic.ch_'.$ch) }}</span>@endforeach <a href="{{ route('licenses.index') }}#m{{ $m->id }}">{{ __('lic.manage') }} →</a></small>
                        <span class="material-fields">
                            <label class="check"><input type="checkbox" name="is_received" value="1" @checked($m->is_received)> {{ __('inf.received') }}</label>
                            <select name="publication_id"><option value="">{{ __('inf.link_publication') }}</option>
                                @foreach($publications as $p)<option value="{{ $p->id }}" @selected($m->publication_id === $p->id)>{{ $p->scheduled_at->format('d.m') }} · {{ $p->platform->label() }} · {{ \Illuminate\Support\Str::limit($p->content->title, 30) }}</option>@endforeach</select>
                            <input type="date" name="license_from" value="{{ $m->license_from?->toDateString() }}" title="{{ __('lic.from') }}" aria-label="{{ __('lic.from') }}">
                            <input type="date" name="license_until" value="{{ $m->license_until?->toDateString() }}" title="{{ __('inf.license_until') }}" aria-label="{{ __('inf.license_until') }}">
                        </span>
                        <details class="mat-replace"><summary class="small">{{ $m->file ? __('lic.replace_file') : __('lic.upload_file') }}</summary>@include('tickets._uploader', ['noSend' => true])</details>
                    </span>
                    <button class="btn btn-small">{{ __('ui.save') }}</button>
                </form>
            @endforeach
            <form method="post" action="{{ route('collaborations.materials.store', $c) }}" class="mat-add stack" data-message-form>
                @csrf
                <h3><x-icon name="upload" :size="16" /> {{ __('lic.add_title') }}</h3>
                <p class="muted small">{{ __('lic.add_hint') }}</p>
                <input name="description" placeholder="{{ __('lic.add_description') }}" maxlength="255">
                @include('tickets._uploader', ['noSend' => true])
                <button class="btn btn-small btn-dark"><x-icon name="plus" :size="14" /> {{ __('inf.add_material') }}</button>
            </form>
        </section>
    </div>
    <aside>
        <form method="post" action="{{ route('collaborations.update', $c) }}" class="card stack" data-message-form>
            @csrf @method('put')
            <h2><x-icon name="file-text" /> {{ __('inf.terms') }}</h2>
            <div class="form-row form-row-2">
                <x-field name="rate" type="number" step="0.01" min="0" :label="__('inf.rate_pln')" :value="$c->rate" required />
                <label class="field"><span>{{ __('inf.billing') }}</span><select name="billing">@foreach(['contract', 'invoice'] as $b)<option value="{{ $b }}" @selected($c->billing === $b)>{{ __('inf.billing_'.$b) }}</option>@endforeach</select></label>
            </div>
            <x-field name="scope" type="textarea" rows="2" :label="__('inf.scope')" :value="$c->scope" />
            <x-field name="license_scope" :label="__('inf.license_scope')" :value="$c->license_scope" />
            <div class="form-row form-row-2">
                <x-field name="license_from" type="date" :label="__('inf.license_from')" :value="$c->license_from?->toDateString()" />
                <x-field name="license_to" type="date" :label="__('inf.license_to')" :value="$c->license_to?->toDateString()" />
            </div>
            <input type="hidden" name="lic_form" value="1">
            <small class="muted">{{ __('lic.collab_defaults') }}</small>
            @include('licenses._channels', ['selected' => $c->license_channels])
            <label class="check"><input type="checkbox" name="requires_takedown" value="1" @checked($c->requires_takedown)> {{ __('lic.requires_takedown') }}</label>
            <label class="check"><input type="checkbox" name="needs_shipment" value="1" @checked($c->needs_shipment)> {{ __('inf.needs_shipment') }}</label>
            <h3>{{ __('inf.sales') }}</h3>
            <x-field name="tracking_link" type="url" :label="__('inf.tracking_link')" :value="$c->tracking_link" />
            <div class="form-row form-row-2">
                <x-field name="discount_code" :label="__('inf.discount_code')" :value="$c->discount_code" />
                <x-field name="sales" type="number" min="0" :label="__('inf.sales_count')" :value="$c->sales" />
            </div>
            <x-field name="revenue" type="number" step="0.01" min="0" :label="__('inf.revenue')" :value="$c->revenue" />
            @if($c->status !== 'done')<x-field name="private_notes" type="textarea" rows="2" :label="__('inf.private_notes')" :value="$c->private_notes" />@endif
            <h3>{{ __('inf.contract_file') }}</h3>
            @if($c->contract)<a href="{{ route('files.show', [$c->contract, $c->contract->name]) }}" class="side-file"><x-icon name="file" :size="16" /><span>{{ $c->contract->name }}</span></a>@endif
            @include('tickets._uploader', ['noSend' => true])
            <button class="btn btn-dark">{{ __('ui.save') }}</button>
        </form>
        @include('influencers._reminders', ['reminders' => $c->reminders, 'type' => 'collaboration', 'id' => $c->id])
        @if($c->status === 'active')
        <details class="card">
            <summary class="muted">{{ __('inf.cancel') }}</summary>
            <form method="post" action="{{ route('collaborations.cancel', $c) }}" class="stack" style="margin-top: .6rem">@csrf
                <input name="cancel_reason" placeholder="{{ __('inf.cancel_reason') }}" required><button class="btn btn-small">{{ __('inf.cancel_confirm') }}</button></form>
        </details>
        @endif
    </aside>
</div>
@endsection
@push('scripts')<script src="{{ asset('js/uploader.js') }}?v=4"></script>@endpush
