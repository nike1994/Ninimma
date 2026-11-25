@extends('layouts.guest')
@section('title', __('ui.recovery_codes'))
@section('content')
<div class="stack">
    <p>{{ __('ui.recovery_codes_hint') }}</p>
    <ul class="codes">@foreach($codes as $c)<li><code>{{ $c }}</code></li>@endforeach</ul>
    <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('ui.continue') }}</a>
</div>
@endsection
