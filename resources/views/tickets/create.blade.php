@extends('layouts.app')
@section('title', __('ui.new_ticket'))
@section('content')
<h1 class="page-title">{{ __('ui.new_ticket') }}</h1>
<form method="post" action="{{ route('tickets.store') }}" class="card stack narrow-lg" data-message-form>
    @csrf
    @if($parent)
        <input type="hidden" name="parent_id" value="{{ $parent->id }}">
        <p class="muted">{{ __('ui.follows_from') }}: <a href="{{ route('tickets.show', $parent) }}">#{{ $parent->id }} {{ $parent->title }}</a></p>
    @elseif($clients->isNotEmpty())
        <label class="field"><span>{{ __('ui.client') }}</span>
            <select name="client_id" required>
                <option value=""></option>
                @foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', request('client')) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
    @endif
    <x-field name="title" :label="__('ui.title')" required maxlength="200" />
    <label class="field"><span>{{ __('ui.type') }}</span>
        <select name="type">
            @foreach(\App\Enums\TicketType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('type', 'task') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </label>
    <x-field name="body" type="textarea" :label="__('ui.description')" rows="6" data-paste-target />
    @include('tickets._uploader', ['noSend' => true])
    <button class="btn btn-send btn-block"><x-icon name="send" :size="18" />{{ __('ui.submit_ticket') }}</button>
</form>
@endsection
@push('scripts')<script src="{{ asset('js/uploader.js') }}?v=2"></script>@endpush
