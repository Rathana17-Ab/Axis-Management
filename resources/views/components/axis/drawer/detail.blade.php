{{-- A read-only label/value pair for "view" drawers. --}}
@props([
    'label',
    'span' => null,
])

<div {{ $attributes->class(['axis-detail', 'sm:col-span-2' => $span === 'full']) }}>
    <dt class="axis-detail__label">{{ $label }}</dt>
    <dd class="axis-detail__value">{{ $slot }}</dd>
</div>
