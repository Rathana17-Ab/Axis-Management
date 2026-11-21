{{--
    One form field: label above the control, optional hint, and an inline error line.
    `name` must match the server-side validation key so 422 errors land under the right input.
--}}
@props([
    'label',
    'for' => null,
    'name' => null,
    'required' => false,
    'span' => null,
    'hint' => null,
])

<div {{ $attributes->class(['axis-field', 'sm:col-span-2' => $span === 'full']) }} @if ($name) data-field="{{ $name }}" @endif>
    <label @if ($for) for="{{ $for }}" @endif class="axis-field__label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-300" aria-hidden="true">*</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="axis-field__hint">{{ $hint }}</p>
    @endif
    @if ($name)
        <p data-error-for="{{ $name }}" class="axis-field__error hidden" role="alert"></p>
    @endif
</div>
