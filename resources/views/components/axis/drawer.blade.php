{{--
    AXIS Universal Drawer — the single right-side drawer shell used by every module.

    Usage:
        <x-axis.drawer id="modal-add-project" mode="create" icon="fa-folder-plus"
                       title="New project" description="…" breadcrumb="Projects"
                       submit="handleAddProject(event)">
            <x-axis.drawer.section title="Basics">
                <x-axis.drawer.field label="Project name" for="add-proj-name" name="name" required span="full">
                    <input id="add-proj-name" class="axis-input" required>
                </x-axis.drawer.field>
            </x-axis.drawer.section>
        </x-axis.drawer>

    Slots: default (scrollable body), headerActions, toolbar (fixed strip under the header), footer (replaces default buttons).
    Behaviour (open/close, validation, busy state, focus, Escape) lives in public/js/axis-drawer.js.
--}}
@props([
    'id',
    'title',
    'description' => null,
    'icon' => null,
    'breadcrumb' => null,
    'size' => 'md',
    'mode' => 'create',
    'submit' => null,
    'primaryAction' => null,
    'submitLabel' => null,
    'busyLabel' => null,
    'cancelLabel' => null,
    'layer' => 240,
    'onClose' => null,
])

@php
    $modeDefaults = [
        'create' => ['Create', 'Creating…'],
        'edit' => ['Save changes', 'Saving…'],
        'view' => ['Edit', 'Opening…'],
        'delete' => ['Delete', 'Deleting…'],
        'assign' => ['Assign', 'Assigning…'],
        'notes' => ['Save note', 'Saving…'],
    ];
    [$defaultSubmitLabel, $defaultBusyLabel] = $modeDefaults[$mode] ?? $modeDefaults['create'];
    $submitLabel ??= $defaultSubmitLabel;
    $busyLabel ??= $defaultBusyLabel;
    $cancelLabel ??= $mode === 'view' ? 'Close' : 'Cancel';
    $hasPrimary = $submit !== null || $primaryAction !== null;
    $panelWidth = [
        'sm' => 'sm:max-w-[480px]',
        'md' => 'sm:max-w-[580px]',
        'lg' => 'sm:max-w-[680px]',
    ][$size] ?? 'sm:max-w-[580px]';
    $panelTag = $submit !== null ? 'form' : 'div';
@endphp

<div id="{{ $id }}"
     data-axis-drawer
     data-mode="{{ $mode }}"
     @if ($onClose) data-on-close="{{ $onClose }}" @endif
     class="axis-drawer"
     style="z-index: {{ $layer }}"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title"
     aria-describedby="{{ $id }}-description"
     hidden>
    <div class="axis-drawer__overlay" data-drawer-close></div>

    <{{ $panelTag }} class="axis-drawer__panel {{ $panelWidth }}" @if ($submit !== null) onsubmit="{{ $submit }}" novalidate @endif>
        <header class="axis-drawer__header">
            <div class="flex items-start gap-3.5 min-w-0">
                @if ($icon)
                    <span class="axis-drawer__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}" data-drawer-icon></i></span>
                @endif
                <div class="min-w-0">
                    <div data-drawer-breadcrumb @class(['text-xs font-medium text-sky-300/80 truncate mb-0.5', 'hidden' => blank($breadcrumb)])>{{ $breadcrumb }}</div>
                    <h2 id="{{ $id }}-title" data-drawer-title class="text-base font-semibold text-white leading-snug">{{ $title }}</h2>
                    <p id="{{ $id }}-description" data-drawer-description @class(['text-sm text-slate-400 mt-1 leading-relaxed', 'hidden' => blank($description)])>{{ $description }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                {{ $headerActions ?? '' }}
                <button type="button" data-drawer-close class="axis-drawer__close" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </header>

        @isset($toolbar)
            <div class="axis-drawer__toolbar">{{ $toolbar }}</div>
        @endisset

        <div class="axis-drawer__body" data-drawer-body>
            <div data-drawer-alert class="axis-drawer__alert hidden" role="alert"></div>
            {{ $slot }}
        </div>

        <footer class="axis-drawer__footer">
            @isset($footer)
                {{ $footer }}
            @else
                <button type="button" data-drawer-close class="btn-glass axis-drawer__btn">{{ $cancelLabel }}</button>
                @if ($hasPrimary)
                    <button type="{{ $submit !== null ? 'submit' : 'button' }}"
                            @if ($primaryAction) onclick="{{ $primaryAction }}" @endif
                            data-drawer-submit
                            data-busy-label="{{ $busyLabel }}"
                            @class(['axis-drawer__btn', 'axis-btn-danger' => $mode === 'delete', 'btn-primary-glass' => $mode !== 'delete'])>
                        <span class="hidden" data-drawer-spinner aria-hidden="true"><i class="fa-solid fa-circle-notch animate-spin"></i></span>
                        <span data-drawer-submit-label>{{ $submitLabel }}</span>
                    </button>
                @endif
            @endisset
        </footer>
    </{{ $panelTag }}>
</div>
