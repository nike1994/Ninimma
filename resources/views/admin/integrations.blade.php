@extends('layouts.app')
@section('title', __('api.title'))
@section('content')
<div class="dash-head">
    <div class="title-with-icon"><span class="title-icon"><x-icon name="zap" :size="26" /></span>
        <div><h1>{{ __('api.title') }}</h1><p class="muted">{{ __('api.subtitle') }}</p></div></div>
</div>
<div class="int-grid">
    @foreach($providers as $p => $cfg)
        <form method="post" action="{{ route('admin.integrations.update') }}" class="card stack int-card">
            @csrf @method('put')
            <input type="hidden" name="provider" value="{{ $p }}">
            <div class="int-head">
                <span class="int-logos">@if($p === 'meta')<x-platform platform="facebook" :size="30" /><x-platform platform="instagram" :size="30" />@else<x-platform platform="tiktok" :size="30" />@endif</span>
                <h2>{{ __('api.'.$p) }}</h2>
                <span @class(['pill', 'pill-done' => $cfg['ready'], 'pill-needs_decision' => $cfg['enabled'] && ! $cfg['ready']])>
                    {{ $cfg['ready'] ? __('api.ready') : ($cfg['enabled'] ? __('api.not_ready') : __('api.disabled')) }}</span>
            </div>
            <label class="switch"><input type="checkbox" name="enabled" value="1" @checked($cfg['enabled'])><span></span> {{ __('api.enabled') }}</label>
            <x-field name="key" :label="__('api.app_id')" :value="$cfg['key']" autocomplete="off" />
            <label class="field"><span>{{ __('api.app_secret') }}</span>
                <input type="password" name="secret" autocomplete="new-password" placeholder="{{ $cfg['has_secret'] ? '•••••••• '.__('api.secret_saved') : '' }}"></label>
            @if($p === 'tiktok')
                <label class="field"><span>{{ __('api.privacy') }}</span>
                    <select name="tiktok_privacy">@foreach(['SELF_ONLY', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'PUBLIC_TO_EVERYONE'] as $o)<option value="{{ $o }}" @selected($tiktokPrivacy === $o)>{{ __('api.privacy_'.$o) }}</option>@endforeach</select></label>
            @endif
            <label class="field"><span>{{ __('api.redirect') }}</span><code class="int-code">{{ $cfg['redirect'] }}</code></label>
            <p class="muted small">{{ __('api.howto_'.$p) }}</p>
            <div class="int-foot"><span class="muted small">{{ __('api.accounts_connected', ['n' => $cfg['accounts']]) }} · <a href="{{ route('social.accounts') }}">{{ __('social.profiles') }} →</a></span>
                <button class="btn btn-dark btn-small">{{ __('ui.save') }}</button></div>
        </form>
    @endforeach
    <form method="post" action="{{ route('admin.integrations.meta-ads') }}" class="card stack int-card">
        @csrf @method('put')
        <div class="int-head">
            <span class="int-logos"><x-platform platform="facebook" :size="30" /></span>
            <h2>{{ __('api.meta_ads') }}</h2>
            <span @class(['pill', 'pill-done' => $metaAds['ready'], 'pill-needs_decision' => $metaAds['enabled'] && ! $metaAds['ready']])>
                {{ $metaAds['ready'] ? __('api.ready') : ($metaAds['enabled'] ? __('api.not_ready') : __('api.disabled')) }}</span>
        </div>
        <label class="switch"><input type="checkbox" name="enabled" value="1" @checked($metaAds['enabled'])><span></span> {{ __('api.enabled') }}</label>
        <label class="field"><span>{{ __('api.meta_ads_token') }}</span>
            <input type="password" name="token" autocomplete="new-password" placeholder="{{ $metaAds['has_token'] ? '•••••••• '.__('api.secret_saved') : '' }}"></label>
        <p class="muted small">{{ __('api.howto_meta_ads') }}</p>
        <div class="int-foot"><span class="muted small">{{ __('api.meta_ads_linked', ['n' => $metaAds['ads']]) }}</span>
            <button class="btn btn-dark btn-small">{{ __('ui.save') }}</button></div>
    </form>
</div>
<form method="post" action="{{ route('admin.integrations.stats') }}" class="card inline-form" style="margin: 1rem 0">
    @csrf @method('put')
    <label class="switch"><input type="checkbox" name="auto_stats" value="1" @checked($autoStats) onchange="this.form.submit()"><span></span> {{ __('api.auto_stats') }}</label>
</form>
@if($recent->isNotEmpty())
<section class="card">
    <h2><x-icon name="history" /> {{ __('api.recent') }}</h2>
    <div class="table-scroll"><table class="table">
        <tbody>@foreach($recent as $r)
            <tr><td><x-platform :platform="$r->platform" :size="18" /> {{ $r->account->name }}</td>
                <td><a href="{{ route('social.contents.edit', $r->content_id) }}">{{ \Illuminate\Support\Str::limit($r->content->title, 50) }}</a></td>
                <td class="small">{{ $r->scheduled_at->format('d.m H:i') }}</td>
                <td>@include('social.contents._api-status', ['p' => $r])</td></tr>
        @endforeach</tbody>
    </table></div>
</section>
@endif
@endsection
