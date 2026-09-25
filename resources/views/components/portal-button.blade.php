@props(['href' => null, 'variant' => 'primary', 'type' => 'button'])
@php($classes = 'portal-button portal-button--'.$variant)
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif