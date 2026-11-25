@extends('layouts.app')
@section('title', $site->exists ? $site->domain : __('site.new'))
@section('content')
<h1 class="page-title">{{ $site->exists ? $site->domain : __('site.new') }}</h1>
<form method="post" action="{{ $site->exists ? route('sites.update', $site) : route('sites.store') }}" class="card stack narrow-lg">
    @csrf @if($site->exists) @method('put') @endif
    <div class="form-row">
        <label class="field"><span>{{ __('ui.client') }}</span><select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $site->client_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></label>
        <x-field name="domain" :label="__('site.domain')" :value="$site->domain" required placeholder="example.pl" />
        <label class="field"><span>{{ __('site.platform') }}</span><select name="platform">@foreach(\App\Models\Site::PLATFORMS as $p)<option value="{{ $p }}" @selected(old('platform', $site->platform) === $p)>{{ __('site.platform_'.$p) }}</option>@endforeach</select></label>
    </div>
    <div class="form-row">
        <x-field name="hosting" :label="__('site.hosting')" :value="$site->hosting" />
        <x-field name="care_hours" type="number" step="0.5" min="0" :label="__('site.care_hours')" :value="$site->care_hours" />
        <label class="field"><span>{{ __('site.checklist') }}</span><select name="checklist_id"><option value="">{{ __('site.default_list') }}</option>@foreach($checklists as $cl)<option value="{{ $cl->id }}" @selected(old('checklist_id', $site->checklist_id) == $cl->id)>{{ $cl->name }}</option>@endforeach</select></label>
    </div>
    <div class="form-row">
        <label class="field"><span>{{ __('site.review_recurrence') }}</span><select name="review_recurrence"><option value="">{{ __('site.no_reviews') }}</option>@foreach(['weekly', 'monthly', 'quarterly'] as $r)<option value="{{ $r }}" @selected(old('review_recurrence', $site->review_recurrence) === $r)>{{ __('site.rec_'.$r) }}</option>@endforeach</select></label>
        <x-field name="next_review_on" type="date" :label="__('site.next_review')" :value="$site->next_review_on?->toDateString()" />
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($site->is_active ?? true)> {{ __('ui.active') }}</label>
    </div>
    <x-field name="notes" type="textarea" rows="3" :label="__('site.notes')" :value="$site->notes" />
    <p class="muted small">{{ __('site.access_hint') }}</p>
    <button class="btn btn-send"><x-icon name="check" :size="18" />{{ __('ui.save') }}</button>
</form>
@endsection
