{{--
    AXIS typography inspector — a compact developer popover for the signed-in workspace.

    The trigger opens a dark inspection card with the resolved font, metric grid,
    full font stack and a live alphabet sample. Copy behaviour lives in
    public/js/axis-workspace.js.

    <x-axis.typography-inspector />
--}}
@php
    $metrics = [
        ['key' => 'fontSize', 'label' => 'Font size', 'value' => '12px', 'copy' => '12px', 'icon' => 'fa-text-height'],
        ['key' => 'lineHeight', 'label' => 'Line height', 'value' => '19px', 'copy' => '19px', 'icon' => 'fa-ruler-horizontal'],
        ['key' => 'letterSpacing', 'label' => 'Letter spacing', 'value' => '-0.11px', 'copy' => '-0.11px', 'icon' => 'fa-text-width'],
        ['key' => 'fontWeight', 'label' => 'Font weight', 'value' => '650', 'copy' => '650', 'icon' => 'fa-bold'],
        ['key' => 'tag', 'label' => 'Tag', 'value' => 'strong', 'copy' => 'strong', 'icon' => 'fa-code'],
        ['key' => 'color', 'label' => 'Color', 'value' => '#132033', 'copy' => '#132033', 'icon' => 'fa-palette', 'swatch' => '#132033'],
    ];
@endphp

<div data-axis-typography-inspector {{ $attributes->class(['relative', 'shrink-0']) }}>
    <button type="button" id="header-typography-btn" onclick="toggleTypographyInspector(this)"
            class="axis-topbar-icon-btn" title="Typography inspector" aria-label="Inspect typography"
            aria-haspopup="dialog" aria-expanded="false" aria-controls="axis-typography-popover">
        <i class="fa-solid fa-font" aria-hidden="true"></i>
    </button>

    <div id="axis-typography-popover" data-typography-popover role="dialog" aria-label="Typography inspector"
         class="hidden fixed left-0 top-0 z-[260] w-[22rem] max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-white/10 bg-[#1e1e1e] text-slate-100 shadow-[0_18px_45px_-12px_rgba(0,0,0,0.85)]">
        <div class="flex items-start justify-between gap-3 border-b border-white/10 px-4 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-300" aria-hidden="true">
                    <i class="fa-solid fa-font text-sm"></i>
                </span>
                <div class="min-w-0">
                    <div class="text-[11px] font-medium text-slate-300">Font name</div>
                    <div data-typography-font-name class="truncate text-sm font-semibold text-white">San Francisco</div>
                    <div data-typography-font-source class="mt-0.5 text-[11px] text-slate-300">System font</div>
                </div>
            </div>
            <button type="button" data-typography-close class="rounded-md p-1 text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400" aria-label="Close typography inspector">
                <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
            </button>
        </div>

        <div class="px-4 py-3">
            <div class="grid grid-cols-3 gap-2">
                @foreach ($metrics as $metric)
                    <button type="button" data-typography-key="{{ $metric['key'] }}" data-typography-copy="{{ $metric['copy'] }}"
                            data-typography-label="{{ $metric['label'] }}"
                            class="rounded-lg border border-white/10 bg-white/[0.03] px-2.5 py-2 text-left transition-colors hover:border-sky-400/40 hover:bg-white/[0.06] focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
                            aria-label="Copy {{ $metric['label'] }}: {{ $metric['value'] }}">
                        <span class="flex items-center gap-1.5 text-[10px] text-slate-300">
                            <i class="fa-solid {{ $metric['icon'] }}" aria-hidden="true"></i>
                            <span class="truncate">{{ $metric['label'] }}</span>
                        </span>
                        <span class="mt-1 flex items-center gap-1.5 text-xs font-semibold text-slate-100">
                            @if (isset($metric['swatch']))
                                <span data-typography-swatch class="h-3 w-3 shrink-0 rounded border border-white/20" style="background-color: {{ $metric['swatch'] }}" aria-hidden="true"></span>
                            @endif
                            <span data-typography-value="{{ $metric['key'] }}" class="truncate">{{ $metric['value'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-3">
                <div class="text-[11px] text-slate-300">Font family</div>
                <button type="button" data-typography-stack
                        data-typography-copy="Inter, ui-sans-serif, system-ui, -apple-system, &quot;Segoe UI&quot;, sans-serif"
                        data-typography-label="Font family"
                        class="mt-1 block w-full break-words rounded-md text-left font-mono text-[11px] leading-4 text-slate-200 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400"
                        aria-label="Copy font family stack">
                    Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif
                </button>
            </div>

            <div class="mt-3">
                <div class="text-[11px] text-slate-300">Alphabet sample</div>
                <div data-typography-sample-wrap class="mt-1.5 rounded-lg bg-[#f5f7fa] px-3 py-2">
                    <strong data-typography-sample class="text-[#132033] [font-family:Inter,ui-sans-serif,system-ui,-apple-system,'Segoe_UI',sans-serif] [font-size:12px] [line-height:19px] [letter-spacing:-0.11px] [font-weight:650]">AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz</strong>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-white/10 px-4 py-3">
            <span class="text-[11px] text-slate-300">Click a value to copy</span>
            <button type="button" data-typography-copy-css data-typography-label="CSS"
                    class="rounded-lg border border-sky-400/25 bg-sky-500/10 px-3 py-1.5 text-[11px] font-semibold text-sky-200 transition-colors hover:bg-sky-500/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400">
                Copy CSS
            </button>
        </div>

        <div class="sr-only" role="status" aria-live="polite" data-typography-status></div>
    </div>
</div>
