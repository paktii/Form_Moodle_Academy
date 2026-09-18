@props(['name'])
@php($extension = in_array($name, ['trash', 'upload']) ? 'png' : 'svg')
<img src="{{ asset('img/icons/'.$name.'.'.$extension) }}" alt="" aria-hidden="true" {{ $attributes->class(['portal-icon']) }}>