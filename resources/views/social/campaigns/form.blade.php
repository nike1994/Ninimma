@extends('layouts.app')
@section('title', $campaign->exists ? $campaign->name : __('social.new_campaign'))
@section('content')
<h1 class="page-title">{{ $campaign->exists ? $campaign->name : __('social.new_campaign') }}</h1>
<div class="grid-main">
    <form method="post" action="{{ $campaign->exists ? route('social.campaigns.update', $campaign) : route('social.campaigns.store') }}" class="card stack">
        @csrf @if($campaign->exists) @method('put') @endif
        <div class="form-row">
            <label class="field"><span>{{ __('ui.client') }}</span>
                <select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $campaign->client_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </label>
            <label class="field"><span>{{ __('social.status') }}</span>
                <select name="status">@foreach(\App\Models\Campaign::STATUSES as $st)<option value="{{ $st }}" @selected(old('status', $campaign->status) === $st)>{{ __('social.cmp_'.$st) }}</option>@endforeach</select>
            </label>
            <x-field name="budget" type="number" step="0.01" min="0" :label="__('social.budget_pln')" :value="$campaign->budget" required />
        </div>
        <x-field name="name" :label="__('social.campaign_name')" :value="$campaign->name" required />
        <x-field name="goal" type="textarea" :label="__('social.goal')" :value="$campaign->goal" rows="3" />
        <div class="form-row">
            <x-field name="starts_on" type="date" :label="__('social.starts_on')" :value="$campaign->starts_on?->toDateString()" />
            <x-field name="ends_on" type="date" :label="__('social.ends_on')" :value="$campaign->ends_on?->toDateString()" />
            <div></div>
        </div>
        <p class="muted small">{{ __('social.results_hint') }}</p>
        <div class="form-row">
            <x-field name="revenue" type="number" step="0.01" min="0" :label="__('social.revenue')" :value="$campaign->revenue" />
            <x-field name="leads" type="number" min="0" :label="__('social.leads')" :value="$campaign->leads" />
            <div></div>
        </div>
        <button class="btn btn-send"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>
    </form>
    @if($campaign->exists)
    <aside>
        <section class="card card-dark">
            <x-waves variant="card" />
            @php($metric = $campaign->headlineMetric())
            <h2>{{ __('social.results') }}</h2>
            <dl class="dark-dl">
                <dt>{{ __('social.budget_short') }}</dt><dd>{{ number_format((float) $campaign->budget, 0, ',', ' ') }} zł</dd>
                <dt>{{ __('social.kpi_reach') }}</dt><dd>{{ \App\Support\Num::short($campaign->reach()) }}</dd>
                <dt>{{ $metric['label'] }}</dt><dd>{{ $metric['value'] }}</dd>
            </dl>
        </section>
        <section class="card">
            <div class="card-head"><h2>{{ __('social.contents') }}</h2>
                <a href="{{ route('social.contents.create', ['client' => $campaign->client_id, 'campaign' => $campaign->id, 'stage' => 'preparing']) }}" class="btn btn-small"><x-icon name="plus" :size="14" /></a></div>
            @forelse($campaign->contents as $c)
                <a href="{{ route('social.contents.edit', $c) }}" class="side-file"><span>{{ $c->title }}</span>
                    @foreach($c->publications as $p)<x-platform :platform="$p->platform" :size="16" />@endforeach</a>
            @empty
                <p class="muted small">{{ __('social.no_campaign_contents') }}</p>
            @endforelse
            <p class="muted small">{{ __('social.influencers_later') }}</p>
        </section>
    </aside>
    @endif
</div>
@endsection
