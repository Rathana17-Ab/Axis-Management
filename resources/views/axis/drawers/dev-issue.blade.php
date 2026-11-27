<x-axis.drawer id="dev-issue-drawer" mode="notes" icon="fa-bug" layer="240"
               breadcrumb="PROJ-1888 · Axis Core System" title="Task notes"
               description="Bugs hit while building this task, and how they were fixed."
               submit="submitDrawerIssueNote(event)" submit-label="Save note" cancel-label="Close"
               on-close="renderTasksTableRows()">
    <x-slot:headerActions>
        <button type="button" onclick="stepDevIssueTask(-1)" class="axis-drawer__close" title="Previous task" aria-label="Previous task"><i class="fa-solid fa-chevron-left text-xs"></i></button>
        <button type="button" onclick="stepDevIssueTask(1)" class="axis-drawer__close" title="Next task" aria-label="Next task"><i class="fa-solid fa-chevron-right text-xs"></i></button>
    </x-slot:headerActions>

    <x-slot:toolbar>
        <div class="grid grid-cols-3 gap-2.5">
            <button type="button" onclick="setDrawerIssueFilter('all')" id="did-tab-all" class="axis-note-tab is-active" aria-pressed="true">
                <div class="text-xs text-slate-300 font-semibold">All notes</div>
                <div id="did-kpi-total" class="text-base font-bold font-mono text-white mt-0.5">0</div>
            </button>
            <button type="button" onclick="setDrawerIssueFilter('open')" id="did-tab-open" class="axis-note-tab" aria-pressed="false">
                <div class="text-xs text-amber-300 font-semibold">Open</div>
                <div id="did-kpi-open" class="text-base font-bold font-mono text-amber-300 mt-0.5">0</div>
            </button>
            <button type="button" onclick="setDrawerIssueFilter('resolved')" id="did-tab-resolved" class="axis-note-tab" aria-pressed="false">
                <div class="text-xs text-emerald-300 font-semibold">Fixed</div>
                <div id="did-kpi-resolved" class="text-base font-bold font-mono text-emerald-300 mt-0.5">0</div>
            </button>
        </div>
    </x-slot:toolbar>

    {{-- Collapsed by default; "Add a note" opens the form in place (renderDevIssueDrawerContent / setIssueComposerOpen) --}}
    <button type="button" id="did-compose-open" onclick="setIssueComposerOpen(true)" class="axis-note-add">
        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
        <span>Add a note</span>
    </button>

    <x-axis.drawer.section id="did-composer" class="axis-note-composer hidden" title="Add a note" data-note-composer>
        <x-slot:description>
            <span id="did-author-pill">Posting as {{ $currentUser?->name }}</span>
        </x-slot:description>
        <x-axis.drawer.field label="Category" for="did-input-type" name="category">
            <select id="did-input-type" class="axis-input">
                <option value="Bug / Blocker">Bug / Blocker</option>
                <option value="API / DB Issue">API / DB Issue</option>
                <option value="UI / State">UI / State</option>
                <option value="SoD / Edge Case">Permissions / edge case</option>
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Severity" for="did-input-severity" name="severity">
            <select id="did-input-severity" class="axis-input">
                <option value="Critical">Critical</option>
                <option value="High" selected>High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="What went wrong?" for="did-input-enc" name="encountered" required span="full">
            <input type="text" id="did-input-enc" placeholder="e.g. The sidebar flickers when switching stores" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="How you fixed it" for="did-input-fix" name="resolution" span="full" hint="Leave blank if you're still on it — the note stays Open.">
            <input type="text" id="did-input-fix" placeholder="e.g. Cached the store list and debounced the switch" class="axis-input"
                   oninput="syncNewIssueStatusFromFix(this.value)">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="did-input-status" name="status">
            <select id="did-input-status" class="axis-input" onchange="this.dataset.touched = 'true'">
                <option value="Open" selected>Open</option>
                <option value="Investigating">Investigating</option>
                <option value="Resolved">Resolved</option>
            </select>
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <div id="did-notes-list" class="space-y-3.5 mt-5"></div>

    <x-slot:footer>
        <button type="button" id="did-footer-close" data-drawer-close class="btn-glass axis-drawer__btn">Close</button>
        <button type="button" id="did-footer-cancel" onclick="setIssueComposerOpen(false)" class="btn-glass axis-drawer__btn hidden">Cancel</button>
        <button type="submit" id="did-footer-save" data-drawer-submit data-busy-label="Saving…" class="axis-drawer__btn btn-primary-glass hidden">
            <span class="hidden" data-drawer-spinner aria-hidden="true"><i class="fa-solid fa-circle-notch animate-spin"></i></span>
            <span data-drawer-submit-label>Save note</span>
        </button>
    </x-slot:footer>

    {{-- Hooks written by renderDevIssueDrawerContent(); the header mirrors the task title --}}
    <span id="did-task-title" class="hidden"></span>
    <span id="did-task-status" class="hidden"></span>
</x-axis.drawer>
