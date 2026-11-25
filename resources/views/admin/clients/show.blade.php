@extends('layouts.app')
@section('title', $client->name)
@section('content')
<header class="client-head">
    <x-avatar :name="$client->name" :size="88" class="avatar-xl" />
    <div class="client-id">
        <h1>{{ $client->name }} @unless($client->is_active)<span class="pill">{{ __('ui.inactive') }}</span>@endunless</h1>
        <p class="muted">{{ $client->company_name ?: '—' }}<br>{{ __('ui.client_since', ['date' => $client->created_at->translatedFormat('F Y')]) }}</p>
    </div>
    <div class="client-contact">
        @if($client->contact_phone)<a href="tel:{{ $client->contact_phone }}"><x-icon name="phone" :size="18" />{{ $client->contact_phone }}</a>@endif
        @if($client->contact_email)<a href="mailto:{{ $client->contact_email }}"><x-icon name="mail" :size="18" />{{ $client->contact_email }}</a>@endif
    </div>
    <div class="client-badges"><a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-small"><x-icon name="edit" :size="15" /> {{ __('ui.edit') }}</a></div>
</header>

<div class="grid-main">
    <div class="card">
        <h2 class="card-title-lg">{{ __('ui.client_tickets') }}</h2>
        <div class="stats stats-3">
            <x-stat icon="play" :value="$counts['active']" :label="__('ui.active')" tone="blue" :href="route('admin.clients.show', [$client, 'status' => 'with_agency'])" />
            <x-stat icon="clock" :value="$counts['waiting']" :label="__('ui.waiting')" tone="amber" :href="route('admin.clients.show', [$client, 'status' => 'waiting_client'])" />
            <x-stat icon="check" :value="$counts['done']" :label="__('status.done')" tone="green" :href="route('admin.clients.show', [$client, 'status' => 'done'])" />
        </div>
        <form class="filters">
            <label class="search-field"><x-icon name="search" :size="18" /><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_ticket') }}"></label>
            <select name="status" onchange="this.form.submit()">
                <option value="">{{ __('ui.all_statuses') }}</option>
                <option value="open" @selected(request('status') === 'open')>{{ __('ui.open') }}</option>
                @foreach(\App\Enums\TicketStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
            <select name="type" onchange="this.form.submit()">
                <option value="">{{ __('ui.all_types') }}</option>
                @foreach(\App\Enums\TicketType::cases() as $t)<option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </form>
        @forelse($tickets as $t) @include('tickets._row', ['hideClient' => true]) @empty <p class="muted">{{ __('ui.nothing_found') }}</p> @endforelse
        <x-pager :paginator="$tickets" />
    </div>

    <aside>
        <section class="card card-dark">
            <x-waves variant="card" />
            <h2>{{ __('ui.client_summary') }}</h2>
            <dl class="dark-dl">
                <dt><x-icon name="layers" :size="18" />{{ __('ui.active_tickets') }}</dt><dd>{{ $counts['active'] + $counts['waiting'] }}</dd>
                <dt><x-icon name="users" :size="18" />{{ __('ui.accounts') }}</dt><dd>{{ $client->users->count() }}</dd>
                <dt><x-icon name="activity" :size="18" />{{ __('ui.last_activity') }}</dt><dd>{{ $lastActivity ? \Illuminate\Support\Carbon::parse($lastActivity)->diffForHumans() : '—' }}</dd>
            </dl>
        </section>

        <section class="card">
            <div class="card-head"><h2><x-icon name="zap" /> {{ __('ui.quick_actions') }}</h2></div>
            <div class="quick">
                <a href="{{ route('tickets.create', ['client' => $client->id]) }}" class="btn btn-dark"><x-icon name="plus" :size="16" />{{ __('ui.new_ticket_short') }}</a>
                <a href="{{ route('admin.clients.edit', $client) }}" class="btn"><x-icon name="edit" :size="16" />{{ __('ui.edit') }}</a>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2><x-icon name="layers" /> {{ __('site.sites') }}</h2><a href="{{ route('sites.create', ['client' => $client->id]) }}" class="btn btn-small"><x-icon name="plus" :size="14" /></a></div>
            @forelse($client->sites as $site)
                <a href="{{ route('sites.show', $site) }}" class="side-file"><x-icon name="code" :size="16" /><span>{{ $site->domain }}</span><small class="muted">{{ __('site.platform_'.$site->platform) }}</small></a>
            @empty
                <p class="muted small">{{ __('ui.nothing_here') }}</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-head"><h2><x-icon name="building" /> {{ __('ui.client_card') }}</h2></div>
            <dl class="dl">
                <dt>{{ __('ui.tax_id') }}</dt><dd>{{ $client->tax_id ?: '—' }}</dd>
                <dt>{{ __('ui.address') }}</dt><dd>{!! nl2br(e($client->address ?: '—')) !!}</dd>
                <dt>{{ __('ui.retention_months') }}</dt><dd>{{ $client->retention_months ?: '—' }}</dd>
            </dl>
        </section>

        <section class="card">
            <div class="card-head"><h2><x-icon name="lock" /> {{ __('arch.credentials') }}</h2></div>
            <p class="muted small">{{ __('arch.credentials_hint') }}</p>
            @foreach($client->credentials as $cr)
                <div class="cred" data-cred>
                    <div class="cred-head"><strong>{{ $cr->label }}</strong>@if($cr->url)<a href="{{ \Illuminate\Support\Str::startsWith($cr->url, 'http') ? $cr->url : 'https://'.$cr->url }}" target="_blank" rel="noopener" class="small">{{ $cr->url }}</a>@endif</div>
                    <div class="cred-body" data-cred-body hidden></div>
                    <div class="btn-row"><button type="button" class="btn btn-small" data-reveal="{{ route('admin.credentials.reveal', $cr) }}"><x-icon name="eye" :size="14" /> {{ __('arch.reveal') }}</button>
                        <form method="post" action="{{ route('admin.credentials.destroy', $cr) }}" onsubmit="return confirm('{{ __('ui.confirm_delete') }}')">@csrf @method('delete')<button class="btn btn-small">✕</button></form></div>
                </div>
            @endforeach
            <details class="invite"><summary class="btn btn-small"><x-icon name="plus" :size="15" /> {{ __('arch.add_credential') }}</summary>
                <form method="post" action="{{ route('admin.credentials.store', $client) }}" class="stack">@csrf
                    <x-field name="label" :label="__('arch.cred_label')" required placeholder="Panel WordPress / FTP / hosting" />
                    <x-field name="url" :label="__('arch.cred_url')" />
                    <x-field name="username" :label="__('arch.cred_user')" autocomplete="off" />
                    <x-field name="secret" type="password" :label="__('arch.cred_secret')" autocomplete="new-password" />
                    <x-field name="notes" type="textarea" rows="2" :label="__('arch.cred_notes')" />
                    <button class="btn btn-dark">{{ __('ui.save') }}</button></form>
            </details>
        </section>

        <form method="post" action="{{ route('admin.clients.arrangements', $client) }}" class="card stack">
            @csrf @method('put')
            <h2><x-icon name="file-text" /> {{ __('arch.arrangements') }}</h2>
            <textarea name="arrangements" rows="4" placeholder="{{ __('arch.arrangements_hint') }}">{{ $client->arrangements }}</textarea>
            <div class="btn-row"><button class="btn btn-small">{{ __('ui.save') }}</button>
                <a href="{{ route('admin.clients.export', $client) }}" class="btn btn-small"><x-icon name="download" :size="14" /> {{ __('arch.export_client') }}</a></div>
        </form>

        <section class="card">
            <div class="card-head"><h2><x-icon name="users" /> {{ __('ui.accounts') }}</h2></div>
            @foreach($client->users as $u)
                <div class="side-file">
                    <x-avatar :name="$u->name" :size="30" />
                    <span>{{ $u->name }}<small class="muted d-block">{{ $u->email }}</small></span>
                    <form method="post" action="{{ route('admin.users.toggle', $u) }}">@csrf @method('patch')<button class="btn btn-small">{{ $u->is_active ? __('ui.deactivate') : __('ui.activate') }}</button></form>
                </div>
            @endforeach
            @foreach($client->invitations as $inv)
                <p class="muted small">{{ $inv->email }} — {{ __('ui.invited_until', ['date' => $inv->expires_at->format('d.m.Y')]) }}</p>
            @endforeach
            <details class="invite">
                <summary class="btn btn-small"><x-icon name="user-plus" :size="15" /> {{ __('ui.invite_person') }}</summary>
                <form method="post" action="{{ route('admin.clients.invite', $client) }}" class="stack">
                    @csrf
                    <x-field name="name" :label="__('ui.name')" required />
                    <x-field name="email" type="email" :label="__('ui.email')" required />
                    <label class="field"><span>{{ __('ui.language') }}</span>
                        <select name="locale">@foreach(config('agency.locales') as $l)<option value="{{ $l }}">{{ __('ui.lang_'.$l) }}</option>@endforeach</select>
                    </label>
                    <button class="btn btn-dark">{{ __('ui.send_invitation') }}</button>
                </form>
            </details>
        </section>
    </aside>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-reveal]').forEach(b => b.addEventListener('click', async () => {
    const box = b.closest('[data-cred]').querySelector('[data-cred-body]');
    if (!box.hidden) { box.hidden = true; box.textContent = ''; return; }
    const r = await (await fetch(b.dataset.reveal, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
    box.textContent = '';
    [['👤', r.username], ['🔑', r.secret], ['📝', r.notes]].filter(x => x[1]).forEach(([i, v]) => { const d = document.createElement('div'); d.textContent = i + ' ' + v; box.appendChild(d); });
    box.hidden = false; setTimeout(() => { box.hidden = true; box.textContent = ''; }, 30000);
}));
</script>
@endpush
