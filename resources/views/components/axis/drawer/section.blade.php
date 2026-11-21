{{-- A titled group of fields inside an AXIS drawer body. Fields lay out in two columns on wider screens. --}}
@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class('axis-drawer__section') }}>
    @if ($title || filled((string) $description))
        <div class="mb-4">
            @if ($title)
                <h3 class="text-sm font-semibold text-white">{{ $title }}</h3>
            @endif
            @if ($description)
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ $description }}</p>
            @endif
        </div>
    @endif
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
        {{ $slot }}
    </div>
</section>
