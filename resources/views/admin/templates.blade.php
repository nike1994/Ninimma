@extends('layouts.app')
@section('title', __('ui.templates'))
@section('content')
<h1 class="page-title">{{ __('ui.templates') }}</h1>
<section class="card">
    <table class="table">
        <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.client') }}</th><th>{{ __('ui.recurrence') }}</th><th>{{ __('ui.next_run') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($templates as $tpl)
            <tr @class(['inactive' => ! $tpl->is_active])>
                <td>{{ $tpl->name }}<div class="small muted">{{ $tpl->title }}</div></td>
                <td>{{ $tpl->client?->name ?? '—' }}</td>
                <td>{{ __('ui.recurrence_'.$tpl->recurrence) }}</td>
                <td>{{ $tpl->next_run_on?->format('Y-m-d') ?? '—' }}</td>
                <td class="actions">
                    <a href="{{ route('admin.templates.index', ['edit' => $tpl->id]) }}" class="btn btn-small">{{ __('ui.edit') }}</a>
                    <form method="post" action="{{ route('admin.templates.run', $tpl) }}" class="inline">@csrf
                        @unless($tpl->client_id)
                            <select name="client_id" required>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        @endunless
                        <button class="btn btn-small">{{ __('ui.create_now') }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.templates.destroy', $tpl) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('delete')<button class="btn btn-small">✕</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ __('ui.nothing_here') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@php($t = $editing ?? new \App\Models\TicketTemplate(['recurrence' => 'none', 'is_active' => true]))
<form method="post" action="{{ $t->exists ? route('admin.templates.update', $t) : route('admin.templates.store') }}" class="card stack narrow">
    @csrf @if($t->exists) @method('put') @endif
    <h2>{{ $t->exists ? __('ui.edit') : __('ui.new_template') }}</h2>
    <x-field name="name" :label="__('ui.name')" :value="$t->name" required />
    <x-field name="title" :label="__('ui.ticket_title')" :value="$t->title" required placeholder="{{ __('ui.template_title_hint') }}" />
    <label class="field"><span>{{ __('ui.type') }}</span>
        <select name="type">@foreach(\App\Enums\TicketType::cases() as $type)<option value="{{ $type->value }}" @selected(old('type', $t->type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select>
    </label>
    <x-field name="body" type="textarea" :label="__('ui.description')" :value="$t->body" rows="5" />
    <label class="field"><span>{{ __('ui.client') }}</span>
        <select name="client_id"><option value="">{{ __('ui.any_client') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $t->client_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
    </label>
    <label class="field"><span>{{ __('ui.recurrence') }}</span>
        <select name="recurrence">@foreach(['none', 'weekly', 'monthly'] as $r)<option value="{{ $r }}" @selected(old('recurrence', $t->recurrence) === $r)>{{ __('ui.recurrence_'.$r) }}</option>@endforeach</select>
    </label>
    <x-field name="next_run_on" type="date" :label="__('ui.next_run')" :value="$t->next_run_on?->format('Y-m-d')" />
    <input type="hidden" name="is_active" value="0">
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> {{ __('ui.active') }}</label>
    <button class="btn btn-dark">{{ __('ui.save') }}</button>
</form>
@endsection
