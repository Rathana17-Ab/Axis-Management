{{--
    AXIS KPI card — the single executive KPI tile used on the workspace dashboards.

    Values, descriptions, status and visuals are filled from live data by JavaScript using the
    `{key}` id prefix (e.g. key="proj-active" → #kpi-proj-active, #kpi-proj-active-desc …).
    The visual slot shows real current-state data only; trend charts need historical data.

    <x-axis.kpi-card key="proj-active" accent="cyan" icon="fa-briefcase" status="Live" title="Active projects">
        <x-slot:visual>…</x-slot:visual>
    </x-axis.kpi-card>
--}}
@props([
    'key',
    'accent' => 'cyan',
    'icon',
    'status',
    'title',
    'description' => '',
    'live' => false,
])

<article {{ $attributes->class(['axis-kpi', 'axis-kpi--'.$accent]) }} aria-labelledby="kpi-{{ $key }}-title" aria-describedby="kpi-{{ $key }}-desc">
    <div class="flex items-start justify-between gap-3">
        <h3 id="kpi-{{ $key }}-title" class="axis-kpi__title">{{ $title }}</h3>
        <span class="axis-kpi__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
    </div>

    <div class="flex items-center gap-2.5 -mt-1">
        <div id="kpi-{{ $key }}" class="axis-kpi__value" data-kpi-value>—</div>
        <span id="kpi-{{ $key }}-status" class="axis-kpi__badge">
            <span @class(['axis-kpi__badge-dot', 'axis-kpi__badge-dot--live' => $live]) aria-hidden="true"></span>
            <span data-kpi-status-label>{{ $status }}</span>
        </span>
    </div>
    <p id="kpi-{{ $key }}-desc" class="axis-kpi__desc">{{ $description }}</p>

    <div id="kpi-{{ $key }}-visual" class="axis-kpi__visual">
        {{ $visual ?? '' }}
    </div>

    <div id="kpi-{{ $key }}-footer" class="axis-kpi__footer">
        {{ $footer ?? '' }}
    </div>
</article>
