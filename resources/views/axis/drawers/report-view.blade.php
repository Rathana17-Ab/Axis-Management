<x-axis.drawer id="modal-report-view" mode="view" icon="fa-file-lines" size="lg"
               breadcrumb="Weekly & Daily Reports" title="Report Details"
               description="Structured testing scope, role verification and hours logged."
               cancel-label="Close">
    <x-slot:headerActions>
        <button type="button" onclick="copyActiveReportTelegram()" class="axis-drawer__close text-sky-400 hover:text-sky-300" title="Copy for Telegram" aria-label="Copy for Telegram">
            <i class="fa-solid fa-copy text-xs"></i>
        </button>
        <button type="button" id="repview-edit-header" onclick="editActiveReport()" class="axis-drawer__close text-amber-400 hover:text-amber-300" title="Edit report" aria-label="Edit report">
            <i class="fa-solid fa-pen text-xs"></i>
        </button>
        <button type="button" id="repview-delete-header" onclick="deleteActiveReport()" class="axis-drawer__close text-rose-400 hover:text-rose-300" title="Delete report" aria-label="Delete report">
            <i class="fa-solid fa-trash text-xs"></i>
        </button>
    </x-slot:headerActions>

    <x-slot:toolbar>
        <div class="flex flex-wrap items-center justify-between gap-2.5 p-3 rounded-xl bg-white/[0.02] border border-white/[0.08]">
            <div class="flex items-center gap-2">
                <span id="repview-type-badge" class="px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">Weekly</span>
                <span id="repview-module-badge" class="px-2.5 py-1 rounded-md text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30">Axis Sales</span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="text-slate-400 flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar text-slate-500"></i>
                    <span id="repview-period-text" class="text-slate-200 font-mono font-medium">10 Nov – 13 Nov 2026</span>
                </span>
                <span class="text-slate-400 flex items-center gap-1.5">
                    <i class="fa-regular fa-clock text-emerald-400"></i>
                    <span id="repview-hours-text" class="text-emerald-300 font-mono font-bold">30h</span>
                </span>
                <span class="text-slate-400 flex items-center gap-1.5">
                    <i class="fa-regular fa-user text-slate-500"></i>
                    <span id="repview-handler-text" class="text-slate-200 font-medium">Kong Rathana</span>
                </span>
            </div>
        </div>
    </x-slot:toolbar>

    <div class="space-y-4">
        {{-- Telegram Quick Copy Strip --}}
        <div class="flex items-center justify-between p-3 rounded-xl bg-sky-500/10 border border-sky-400/25">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 text-sky-300 flex items-center justify-center text-sm">
                    <i class="fa-brands fa-telegram"></i>
                </div>
                <div>
                    <div class="text-xs font-semibold text-white">Ready for Telegram</div>
                    <div class="text-[11px] text-slate-400">Standardized formatting with roles, progress &amp; page references.</div>
                </div>
            </div>
            <button type="button" onclick="copyActiveReportTelegram()" class="btn-primary-glass px-3.5 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                <i class="fa-solid fa-copy text-xs"></i>
                <span>Copy for Telegram</span>
            </button>
        </div>

        {{-- Structured Sections Container --}}
        <div id="repview-sections-container" class="space-y-3.5"></div>

        {{-- Raw Telegram Message Accordion --}}
        <div class="p-3.5 rounded-xl bg-slate-950/70 border border-white/[0.08] space-y-2">
            <div class="flex items-center justify-between cursor-pointer" onclick="toggleRawTelegramPreview()">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-300">
                    <i class="fa-solid fa-code text-slate-500"></i>
                    <span>Formatted Telegram text</span>
                </div>
                <span class="text-slate-500 text-xs flex items-center gap-1">
                    <span id="repview-raw-hint">Show text</span>
                    <i id="repview-raw-chevron" class="fa-solid fa-chevron-down text-[10px]"></i>
                </span>
            </div>
            <pre id="repview-raw-text" class="hidden text-xs font-mono text-slate-300 whitespace-pre-wrap bg-slate-950 p-3 rounded-lg border border-white/[0.05] max-h-60 overflow-y-auto"></pre>
        </div>

        {{-- Blockers & Notes --}}
        <div id="repview-blockers-box" class="p-3 rounded-xl bg-white/[0.02] border border-white/[0.08] text-xs">
            <span class="text-slate-400 font-semibold">Blockers / Notes: </span>
            <span id="repview-blockers-text" class="text-slate-200">None</span>
        </div>
    </div>

    <x-slot:footer>
        <div class="flex items-center justify-between w-full">
            <button type="button" id="repview-delete-footer" onclick="deleteActiveReport()" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-trash text-xs"></i>
                <span>Delete</span>
            </button>
            <div class="flex items-center gap-2">
                <button type="button" id="repview-edit-footer" onclick="editActiveReport()" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-amber-300 bg-amber-500/15 hover:bg-amber-500/25 border border-amber-400/30 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-pen text-xs"></i>
                    <span>Edit</span>
                </button>
                <button type="button" onclick="closeModal('modal-report-view')" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-300 bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </x-slot:footer>
</x-axis.drawer>
