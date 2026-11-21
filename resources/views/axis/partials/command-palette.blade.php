{{-- Global search / command palette. Opened with ⌘K / Ctrl+K or the header search pill; logic in axis-workspace.js (AxisSearch). --}}
<div id="axis-command-palette" class="axis-palette" role="dialog" aria-modal="true" aria-label="Search" hidden>
    <div class="axis-palette__overlay" onclick="closeCommandPalette()"></div>
    <div class="axis-palette__panel">
        <div class="axis-palette__input-row">
            <i class="fa-solid fa-magnifying-glass text-sky-400" aria-hidden="true"></i>
            <input type="text" id="axis-palette-input" class="axis-palette__input"
                   placeholder="Search projects, modules, tasks, notes, people…"
                   role="combobox" aria-expanded="true" aria-controls="axis-palette-results" aria-autocomplete="list"
                   autocomplete="off" spellcheck="false">
            <kbd class="axis-palette__kbd" aria-hidden="true">Esc</kbd>
        </div>
        <div id="axis-palette-results" class="axis-palette__results" role="listbox" aria-label="Search results"></div>
        <div class="axis-palette__footer">
            <div class="flex items-center gap-3">
                <span><kbd class="axis-palette__kbd">↑</kbd><kbd class="axis-palette__kbd">↓</kbd> to move</span>
                <span><kbd class="axis-palette__kbd">↵</kbd> to open</span>
                <span class="hidden sm:inline"><kbd class="axis-palette__kbd">Esc</kbd> to close</span>
            </div>
            <span id="axis-palette-count" aria-live="polite"></span>
        </div>
    </div>
</div>
