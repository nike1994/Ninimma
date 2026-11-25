@extends('layouts.app')
@section('title', $influencer->name)
@section('content')
<header class="client-head">
    @include('influencers._photo', ['inf' => $influencer, 'size' => 96])
    <div class="client-id">
        <h1>{{ $influencer->name }} @unless($influencer->is_active)<span class="pill">{{ __('ui.inactive') }}</span>@endunless</h1>
        <p class="muted">@if($influencer->rating)<span class="stars">{{ str_repeat('★', $influencer->rating) }}<i>{{ str_repeat('★', 5 - $influencer->rating) }}</i></span> · @endif
            {{ \App\Support\Num::short($influencer->followers()) }} {{ __('inf.followers') }}
            @foreach($influencer->tags ?? [] as $t) <span class="tag">#{{ $t }}</span>@endforeach</p>
    </div>
    <div class="client-contact">
        @if($influencer->phone)<a href="tel:{{ $influencer->phone }}"><x-icon name="phone" :size="18" />{{ $influencer->phone }}</a>@endif
        @if($influencer->email)<a href="mailto:{{ $influencer->email }}"><x-icon name="mail" :size="18" />{{ $influencer->email }}</a>@endif
    </div>
    <div class="client-badges"><a href="{{ route('influencers.edit', $influencer) }}" class="btn btn-small"><x-icon name="edit" :size="15" /> {{ __('ui.edit') }}</a></div>
</header>
<div class="grid-main">
    <div>
        <section class="card">
            <h2>{{ __('inf.collaborations') }}</h2>
            @forelse($influencer->collaborations as $c)
                <a href="{{ route('collaborations.show', $c) }}" class="collab-row">
                    <span><strong>{{ $c->campaign->name }}</strong><small class="muted">{{ $c->campaign->client->name }} · {{ number_format((float) $c->rate, 0, ',', ' ') }} zł · {{ __('inf.billing_'.$c->billing) }}</small></span>
                    <span>@include('influencers._stages')<small class="muted">{{ __('inf.stage_'.$c->stage()) }}</small></span>
                </a>
            @empty
                <p class="muted">{{ __('inf.no_collabs') }}</p>
            @endforelse
            <details class="quote-form">
                <summary><x-icon name="plus" :size="16" /> <strong>{{ __('inf.new_collab') }}</strong></summary>
                <form method="post" action="{{ route('collaborations.store') }}" class="stack">
                    @csrf<input type="hidden" name="influencer_id" value="{{ $influencer->id }}">
                    <div class="form-row">
                        <label class="field"><span>{{ __('inf.campaign') }}</span><select name="campaign_id" required>@foreach($campaigns as $cmp)<option value="{{ $cmp->id }}">{{ $cmp->name }} · {{ $cmp->client->name }}</option>@endforeach</select></label>
                        <x-field name="rate" type="number" step="0.01" min="0" :label="__('inf.rate_pln')" required />
                        <label class="field"><span>{{ __('inf.billing') }}</span><select name="billing"><option value="contract">{{ __('inf.billing_contract') }}</option><option value="invoice">{{ __('inf.billing_invoice') }}</option></select></label>
                    </div>
                    <x-field name="scope" type="textarea" rows="2" :label="__('inf.scope')" :placeholder="__('inf.scope_hint')" />
                    <div class="form-row">
                        <x-field name="license_scope" :label="__('inf.license_scope')" />
                        <x-field name="license_from" type="date" :label="__('inf.license_from')" />
                        <x-field name="license_to" type="date" :label="__('inf.license_to')" />
                    </div>
                    <label class="check"><input type="checkbox" name="needs_shipment" value="1" checked> {{ __('inf.needs_shipment') }}</label>
                    <button class="btn btn-dark">{{ __('ui.save') }}</button>
                </form>
            </details>
        </section>
    </div>
    <aside>
        <section class="card">
            <h2><x-icon name="link" /> {{ __('inf.profiles') }}</h2>
            @foreach($influencer->profiles ?? [] as $p)
                <a href="{{ $p['url'] ?? '#' }}" target="_blank" rel="noopener" class="side-file">@if(!empty($p['platform']))<x-platform :platform="$p['platform']" :size="20" />@endif<span>{{ $p['handle'] ?? $p['url'] }}</span><small class="muted">{{ \App\Support\Num::short((int) ($p['followers'] ?? 0)) }}</small></a>
            @endforeach
            @if($influencer->portfolio_url)<a href="{{ $influencer->portfolio_url }}" target="_blank" rel="noopener" class="side-file"><x-icon name="image" :size="18" /><span>{{ __('inf.portfolio') }}</span></a>@endif
        </section>
        <section class="card">
            <h2><x-icon name="upload" /> {{ __('inf.shipping') }}</h2>
            <p class="small">{!! nl2br(e($influencer->address ?: '—')) !!}</p>
            @if($influencer->parcel_locker)<p class="small"><strong>{{ __('inf.parcel_locker') }}:</strong> {{ $influencer->parcel_locker }}</p>@endif
            @if($influencer->notes)<p class="small muted">{!! nl2br(e($influencer->notes)) !!}</p>@endif
        </section>
        @include('influencers._reminders', ['reminders' => $influencer->reminders, 'type' => 'influencer', 'id' => $influencer->id])
    </aside>
</div>
@endsection
