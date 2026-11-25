@extends('layouts.guest')
@section('title', __('ui.two_factor_setup'))
@section('content')
<form method="post" action="{{ url('/two-factor/setup') }}" class="stack">
    @csrf
    <p>{{ __('ui.two_factor_setup_hint') }}</p>
    <div class="secret"><code>{{ trim(chunk_split($secret, 4, ' ')) }}</code></div>
    <details><summary class="muted">otpauth://</summary><code class="break">{{ $uri }}</code></details>
    <x-field name="code" :label="__('ui.code')" required autofocus autocomplete="one-time-code" inputmode="numeric" />
    <button class="btn btn-primary">{{ __('ui.verify') }}</button>
</form>
@endsection
