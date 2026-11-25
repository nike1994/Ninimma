@extends('layouts.app')
@section('title', $client->exists ? $client->name : __('ui.new_client'))
@section('content')
<h1>{{ $client->exists ? $client->name : __('ui.new_client') }}</h1>
<form method="post" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}" class="card stack narrow">
    @csrf @if($client->exists) @method('put') @endif
    <x-field name="name" :label="__('ui.name')" :value="$client->name" required />
    <x-field name="company_name" :label="__('ui.company_name')" :value="$client->company_name" />
    <x-field name="tax_id" :label="__('ui.tax_id')" :value="$client->tax_id" />
    <x-field name="address" type="textarea" :label="__('ui.address')" :value="$client->address" rows="2" />
    <x-field name="contact_email" type="email" :label="__('ui.contact_email')" :value="$client->contact_email" />
    <x-field name="contact_phone" :label="__('ui.contact_phone')" :value="$client->contact_phone" />
    <x-field name="retention_months" type="number" :label="__('ui.retention_months')" :value="$client->retention_months" min="1" />
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active))> {{ __('ui.active') }}</label>
    <button class="btn btn-primary">{{ __('ui.save') }}</button>
</form>
@endsection
