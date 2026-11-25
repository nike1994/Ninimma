@extends('layouts.app')
@section('title', __('site.checklists'))
@section('content')
<h1 class="page-title">{{ __('site.checklists') }}</h1>
<div class="grid-2">
    @foreach($checklists as $cl)
        <form method="post" action="{{ route('checklists.update', $cl) }}" class="card stack">
            @csrf @method('put')
            <div class="form-row form-row-2"><x-field name="name" :label="__('ui.name')" :value="$cl->name" required />
                <label class="field"><span>{{ __('site.platform') }}</span><select name="platform"><option value="">—</option>@foreach(\App\Models\Site::PLATFORMS as $p)<option value="{{ $p }}" @selected($cl->platform === $p)>{{ __('site.platform_'.$p) }}</option>@endforeach</select></label></div>
            <x-field name="items" type="textarea" rows="8" :label="__('site.items_hint')" :value="implode(PHP_EOL, $cl->items)" required />
            <button class="btn btn-small">{{ __('ui.save') }}</button>
        </form>
    @endforeach
    <form method="post" action="{{ route('checklists.store') }}" class="card stack">
        @csrf
        <h2><x-icon name="plus" /> {{ __('site.new_checklist') }}</h2>
        <div class="form-row form-row-2"><x-field name="name" :label="__('ui.name')" required />
            <label class="field"><span>{{ __('site.platform') }}</span><select name="platform"><option value="">—</option>@foreach(\App\Models\Site::PLATFORMS as $p)<option value="{{ $p }}">{{ __('site.platform_'.$p) }}</option>@endforeach</select></label></div>
        <x-field name="items" type="textarea" rows="8" :label="__('site.items_hint')" :value="implode(PHP_EOL, __('site.default_checklist'))" required />
        <button class="btn btn-dark">{{ __('ui.save') }}</button>
    </form>
</div>
@endsection
