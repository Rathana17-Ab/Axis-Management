{{--
    Start → due date field for forms. The values live in two hidden inputs (start-id / end-id) so the
    existing submit code keeps reading them; the button opens the shared AxisDatePicker (axis-workspace.js).

    <x-axis.date-range-field id="new-task-schedule" start-id="new-task-start" end-id="new-task-end"
                             :start="now()->toDateString()" :end="now()->addWeeks(2)->toDateString()" />
--}}
@props([
    'id',
    'startId',
    'endId',
    'start' => null,
    'end' => null,
])

@php
    $format = fn (?string $date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('j M Y') : 'Not set';
@endphp

<input type="hidden" id="{{ $startId }}" value="{{ $start }}">
<input type="hidden" id="{{ $endId }}" value="{{ $end }}">
<button type="button" id="{{ $id }}" data-date-range data-start-input="{{ $startId }}" data-end-input="{{ $endId }}"
        onclick="openDateRangeField(this)" aria-haspopup="dialog" aria-expanded="false"
        {{ $attributes->class(['axis-date-range-field']) }}>
    <i class="fa-regular fa-calendar text-sm text-slate-400" aria-hidden="true"></i>
    <span class="flex items-center gap-2 min-w-0">
        <span class="axis-date-range-field__part">
            <span class="axis-date-range-field__label">Start</span>
            <span data-range-start class="axis-date-range-field__value">{{ $format($start) }}</span>
        </span>
        <span class="text-slate-500" aria-hidden="true">→</span>
        <span class="axis-date-range-field__part">
            <span class="axis-date-range-field__label">Deadline</span>
            <span data-range-end class="axis-date-range-field__value">{{ $format($end) }}</span>
        </span>
    </span>
    <span data-range-span class="ml-auto text-xs text-slate-400 whitespace-nowrap"></span>
</button>
