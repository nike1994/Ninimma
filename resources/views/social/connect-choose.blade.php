@extends('layouts.app')
@section('title', __('api.choose_title', ['name' => $account->name]))
@section('content')
<h1 class="page-title">{{ __('api.choose_title', ['name' => $account->name]) }}</h1>
<form method="post" action="{{ route('social.connect.attach') }}" class="card stack" style="max-width: 640px">
    @csrf
    <p class="muted small">{{ __('api.choose_hint') }}</p>
    @foreach($pages as $pg)
        @php($disabled = $account->platform === \App\Enums\Platform::Instagram && ! $pg['ig_id'])
        <label @class(['mat-opt', 'locked' => $disabled])>
            <input type="radio" name="page" value="{{ $pg['id'] }}" @disabled($disabled) required>
            <span class="mat-opt-body"><strong>{{ $pg['name'] }}</strong>
                <small class="muted">@if($pg['ig_id'])<x-platform platform="instagram" :size="14" /> {{ '@'.$pg['ig_username'] }}@else{{ __('api.no_instagram', ['page' => $pg['name']]) }}@endif</small></span>
        </label>
    @endforeach
    <button class="btn btn-dark">{{ __('api.choose') }}</button>
</form>
@endsection
