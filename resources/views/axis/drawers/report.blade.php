<x-axis.drawer id="modal-report" mode="create" icon="fa-file-lines" size="lg"
               breadcrumb="Reports" title="New report"
               description="Share structured progress, test cases, pages tested and blockers."
               submit="handleSaveReport(event)" submit-label="Send report" busy-label="Sending…">
    <input type="hidden" id="rep-modal-id" value="">

    <x-slot:toolbar>
        <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-950/60 border border-white/[0.08]">
            <button type="button" id="rep-mode-paste-tab" onclick="setReportDrawerMode('paste')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30 transition-all flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                <span>Smart Telegram Paste</span>
            </button>
            <button type="button" id="rep-mode-builder-tab" onclick="setReportDrawerMode('builder')" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-sliders text-xs"></i>
                <span>Structured Form</span>
            </button>
        </div>
    </x-slot:toolbar>

    {{-- Mode 1: Smart Telegram Paste --}}
    <div id="rep-panel-paste" class="space-y-4">
        <div class="p-3.5 rounded-xl bg-sky-500/10 border border-sky-400/25 space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-brands fa-telegram text-sky-400 text-sm"></i>
                    <span class="text-xs font-bold text-white">Paste Telegram Weekly / Daily Message</span>
                </div>
                <span class="text-[11px] text-sky-300 font-medium">Auto-detects roles, dates &amp; items</span>
            </div>
            <p class="text-[11px] text-slate-300">
                Paste your team's Telegram standup or weekly message below. Axis will automatically parse the dates, roles, test codes (TL 04, SM 05), percentages, and hours logged.
            </p>
        </div>

        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <label for="rep-telegram-paste-text" class="text-xs font-medium text-slate-300">Raw Telegram message</label>
                <button type="button" onclick="loadSampleTelegramReport()" class="text-[11px] text-sky-400 hover:text-sky-300 font-medium flex items-center gap-1">
                    <i class="fa-solid fa-lightbulb text-[10px]"></i>
                    <span>Load sample report</span>
                </button>
            </div>
            <textarea id="rep-telegram-paste-text" rows="10" placeholder="Dear Bong and team,&#10;Here is my weekly report (10 Nov – 13 Nov 2026):&#10;&#10;I Axis system - Axis Sales&#10;1. Tested and Fixed on Team Lead Role:&#10;+ TL 04 — Watch the team on the Team Sales Dashboard... : (100% | 0%)&#10;- P58 · Team Sales Dashboard&#10;🕔 Total time used: 30 hours&#10;Thank you." class="axis-input font-mono text-xs leading-relaxed"></textarea>
        </div>

        <div class="flex items-center justify-between gap-2 pt-1">
            <button type="button" onclick="parseTelegramText()" class="btn-primary-glass px-4 py-2 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                <span>Parse &amp; Auto-fill Fields</span>
            </button>
            <span id="rep-parse-status" class="text-xs text-slate-400 italic">Ready to parse</span>
        </div>
    </div>

    {{-- Common Form Fields (used by both modes, auto-filled by smart parser) --}}
    <x-axis.drawer.section title="Report Details">
        <x-axis.drawer.field label="Report Type" for="rep-modal-type" name="report_type" required>
            <select id="rep-modal-type" onchange="handleReportTypeChange()" class="axis-input" required>
                <option value="Weekly">Weekly report</option>
                <option value="Daily">Daily report</option>
            </select>
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Developer / Reporter" for="rep-modal-handler" name="handler_name" required>
            <select id="rep-modal-handler" class="axis-input" required>
                @foreach ($handlers as $handler)
                    <option value="{{ $handler }}" @selected($currentUser?->name === $handler)>{{ $handler }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Project / System" for="rep-modal-module" name="module_name" required>
            <select id="rep-modal-module" class="axis-input" required>
                @foreach ($projects as $project)
                    <option value="{{ $project->name }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Recipient" for="rep-modal-recipient" name="recipient" span="full">
            <input type="text" id="rep-modal-recipient" value="Dear Bong and team" class="axis-input">
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Date From" for="rep-modal-date-from" name="date_from">
            <input type="date" id="rep-modal-date-from" class="axis-input">
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Date To" for="rep-modal-date-to" name="date_to">
            <input type="date" id="rep-modal-date-to" class="axis-input">
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Total Hours" for="rep-modal-hours" name="hours_logged">
            <input type="number" step="0.5" min="0" max="999" id="rep-modal-hours" value="30" class="axis-input">
        </x-axis.drawer.field>

        <x-axis.drawer.field label="Blockers" for="rep-modal-blocker" name="blocker">
            <input type="text" id="rep-modal-blocker" value="None" class="axis-input" maxlength="255">
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    {{-- Structured Section Builder / Item Manager --}}
    <x-axis.drawer.section title="Structured Verification &amp; Test Cases" id="rep-section-builder-box">
        <div class="sm:col-span-2 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs text-slate-400">Roles, test cases, and progress breakdown</span>
                <button type="button" onclick="addReportSection()" class="text-xs text-sky-400 hover:text-sky-300 font-semibold flex items-center gap-1">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add section / role</span>
                </button>
            </div>

            <div id="rep-sections-list" class="space-y-3">
                {{-- Dynamically populated sections with items --}}
            </div>
        </div>
    </x-axis.drawer.section>

    {{-- Text Summary (Always synchronized for fallback and instant Telegram copy) --}}
    <x-axis.drawer.section title="Formatted Telegram Summary">
        <x-axis.drawer.field label="Telegram Message Content" for="rep-modal-summary" name="summary" required span="full">
            <textarea id="rep-modal-summary" rows="8" placeholder="Formatted report text will be generated here..." class="axis-input font-mono text-xs leading-relaxed" required></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <x-slot:footer>
        <div class="flex items-center justify-between w-full">
            <button type="button" onclick="copyReportDrawerTelegram()" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-sky-300 bg-sky-500/15 hover:bg-sky-500/25 border border-sky-400/30 transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-copy text-xs"></i>
                <span>Copy for Telegram</span>
            </button>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeModal('modal-report')" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-300 bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="btn-primary-glass px-4 py-1.5 text-xs font-semibold flex items-center gap-1.5" data-drawer-submit>
                    <i class="fa-solid fa-paper-plane text-[10px]"></i>
                    <span data-drawer-submit-label>Send report</span>
                </button>
            </div>
        </div>
    </x-slot:footer>
</x-axis.drawer>
