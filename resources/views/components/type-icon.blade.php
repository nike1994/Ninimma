@props(['type'])
@php($map = ['bug' => 'bug', 'task' => 'check-circle', 'article' => 'file-text', 'campaign' => 'megaphone', 'site_review' => 'code', 'seo_content' => 'search', 'other' => 'folder', 'social' => 'megaphone'])
<span {{ $attributes->merge(['class' => 'type-tile type-'.$type->value]) }}><x-icon :name="$map[$type->value] ?? 'folder'" :size="20" /></span>
