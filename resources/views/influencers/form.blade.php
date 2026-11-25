@extends('layouts.app')
@section('title', $influencer->exists ? $influencer->name : __('inf.new_person'))
@section('content')
<h1 class="page-title">{{ $influencer->exists ? $influencer->name : __('inf.new_person') }}</h1>
<form method="post" action="{{ $influencer->exists ? route('influencers.update', $influencer) : route('influencers.store') }}" class="card stack narrow-lg" data-message-form>
    @csrf @if($influencer->exists) @method('put') @endif
    <div class="form-row">
        <x-field name="name" :label="__('inf.full_name')" :value="$influencer->name" required />
        <x-field name="email" type="email" :label="__('ui.email')" :value="$influencer->email" />
        <x-field name="phone" :label="__('ui.contact_phone')" :value="$influencer->phone" />
    </div>
    <h3>{{ __('inf.profiles') }}</h3>
    @php($profiles = array_pad($influencer->profiles ?? [], max(3, count($influencer->profiles ?? []) + 1), []))
    @foreach($profiles as $i => $p)
        <div class="quote-opt-row">
            <select name="profiles[{{ $i }}][platform]"><option value="">—</option>@foreach(\App\Enums\Platform::cases() as $pl)<option value="{{ $pl->value }}" @selected(($p['platform'] ?? '') === $pl->value)>{{ $pl->label() }}</option>@endforeach</select>
            <input name="profiles[{{ $i }}][handle]" value="{{ $p['handle'] ?? '' }}" placeholder="@profil">
            <input name="profiles[{{ $i }}][followers]" type="number" min="0" value="{{ $p['followers'] ?? '' }}" placeholder="{{ __('inf.followers') }}">
            <input name="profiles[{{ $i }}][url]" type="url" value="{{ $p['url'] ?? '' }}" placeholder="https://">
        </div>
    @endforeach
    <x-field name="portfolio_url" type="url" :label="__('inf.portfolio')" :value="$influencer->portfolio_url" />
    <h3>{{ __('inf.shipping') }}</h3>
    <div class="form-row form-row-2">
        <x-field name="address" type="textarea" rows="2" :label="__('ui.address')" :value="$influencer->address" />
        <x-field name="parcel_locker" :label="__('inf.parcel_locker')" :value="$influencer->parcel_locker" placeholder="np. WAW123M" />
    </div>
    <div class="form-row">
        <x-field name="tags" :label="__('inf.tags')" :value="implode(', ', $influencer->tags ?? [])" placeholder="uroda, fitness, eko" />
        <label class="field"><span>{{ __('inf.rating') }}</span>
            <select name="rating"><option value="">—</option>@for($r = 1; $r <= 5; $r++)<option value="{{ $r }}" @selected($influencer->rating === $r)>{{ str_repeat('★', $r) }}</option>@endfor</select></label>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($influencer->is_active ?? true)> {{ __('ui.active') }}</label>
    </div>
    <x-field name="notes" type="textarea" rows="3" :label="__('inf.notes')" :value="$influencer->notes" />
    <h3>{{ __('inf.photo') }}</h3>
    @include('tickets._uploader', ['noSend' => true])
    <button class="btn btn-send"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>
</form>
@endsection
@push('scripts')<script src="{{ asset('js/uploader.js') }}?v=2"></script>@endpush
