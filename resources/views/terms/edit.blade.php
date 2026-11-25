@extends('layouts.app')
@section('title', __('ui.terms'))
@section('content')
<h1 class="page-title">{{ __('ui.terms') }} <small class="muted">v{{ $version }}</small></h1>
<form method="post" action="{{ route('admin.terms.edit') }}" class="card stack narrow-lg">
    @csrf @method('put')
    <p class="muted small">{{ __('ui.terms_hint') }}</p>
    <textarea name="terms" rows="20" required>{{ old('terms', $terms) }}</textarea>
    <label class="check"><input type="checkbox" name="new_version" value="1"> {{ __('ui.terms_bump') }}</label>
    <button class="btn btn-dark">{{ __('ui.save') }}</button>
</form>
@endsection
