@extends('layouts.app')
@section('title', __('ui.files'))
@section('content')
<h1 class="page-title">{{ __('ui.files') }}</h1>
<section class="card">
    @forelse($files as $usage) @include('files._item', ['withTicket' => true]) @empty <p class="muted">{{ __('ui.nothing_here') }}</p> @endforelse
</section>
<x-pager :paginator="$files" />
@endsection
