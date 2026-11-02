@props(['title'])
<section {{ $attributes->class(['portal-card', 'attachment-card']) }}>
    <h3>{{ $title }}</h3>
    {{ $slot }}
</section>
