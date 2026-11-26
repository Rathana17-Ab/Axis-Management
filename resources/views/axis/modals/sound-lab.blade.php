    {{-- Sound Lab: hear every interface sound. State (style, mute, last played) is synced by SoundFX.syncUI(). --}}
    @php
        /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string|null}> $labSounds [label, hint, SoundFX call, icon, tone] */
        $labSounds = [
            ['Hover', 'Moving over something', 'SoundFX.hoverTick(-0.35)', 'fa-feather-pointed', null],
            ['Click', 'Pressing a button', 'SoundFX.buttonTap(0)', 'fa-hand-pointer', null],
            ['Typing', 'Searching or writing in a field', 'SoundFX.keyTick()', 'fa-keyboard', null],
            ['Change page', 'Moving between pages', 'SoundFX.navSwitch(2)', 'fa-compass', null],
            ['Pick an option', 'Choosing from a dropdown', 'SoundFX.dropdownSelect()', 'fa-list-check', null],
            ['Toggle', 'Turning something on or off', 'SoundFX.toggleSwitch(true)', 'fa-toggle-on', null],
            ['Slider', 'Dragging the progress slider', 'SoundFX.sliderScrub(78)', 'fa-sliders', null],
            ['Open window', 'Opening a form or panel', 'SoundFX.modalOpen()', 'fa-window-restore', null],
            ['Close window', 'Closing a form or panel', 'SoundFX.modalClose()', 'fa-xmark', null],
            ['Start timer', 'Starting a task timer', 'SoundFX.timerStart()', 'fa-play', null],
            ['Pause timer', 'Pausing a task timer', 'SoundFX.timerPause()', 'fa-pause', null],
            ['Timer tick', 'Each second, if ticking is on', 'SoundFX.chronoTick(2, true)', 'fa-stopwatch', null],
            ['Task done', 'Finishing a task or fixing an issue', 'SoundFX.taskComplete()', 'fa-circle-check', 'success'],
            ['Saved', 'Creating something new', 'SoundFX.createSuccess()', 'fa-circle-plus', 'success'],
            ['Clear filters', 'Resetting filters', 'SoundFX.filterReset()', 'fa-filter-circle-xmark', null],
            ['Delete', 'Deleting something', 'SoundFX.deleteTrash()', 'fa-trash-can', 'danger'],
            ['Heads up', 'Warnings, locked accounts, reopened issues', 'SoundFX.sodAlert()', 'fa-triangle-exclamation', 'warning'],
        ];
    @endphp
    <div id="modal-sound-lab" class="axis-sound-lab-overlay fixed inset-0 z-[245] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="sound-lab-title" onclick="if (event.target === this) closeModal('modal-sound-lab')">
        <div class="axis-sound-lab w-full max-w-3xl flex flex-col max-h-[calc(100dvh-2rem)]">
            <div class="flex items-start justify-between gap-3 pb-4 border-b border-white/[0.08]">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="axis-sound-lab__icon" aria-hidden="true"><i class="fa-solid fa-wave-square text-sm"></i></span>
                    <div class="min-w-0">
                        <h3 id="sound-lab-title" class="text-sm font-bold text-white">Sounds</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Every click, toggle and timer has its own short sound. Tap one to hear it.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('modal-sound-lab')" data-no-click-sfx="true" data-autofocus class="axis-topbar-icon-btn shrink-0" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-4">
                <div class="text-xs min-w-0">
                    <div id="lab-playing-row">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400">Last played:</span>
                            <span id="lab-last-sound-name" class="font-semibold text-white truncate">Nothing yet</span>
                        </div>
                    </div>
                    <div id="lab-muted-note" class="hidden">
                        <div class="flex items-center gap-2 text-slate-300">
                            <i class="fa-solid fa-volume-xmark text-slate-400" aria-hidden="true"></i>
                            <span>Sound is off, so nothing will play.</span>
                            <button type="button" onclick="SoundFX.toggleMute()" data-no-click-sfx="true" class="font-semibold text-[color:var(--sales-signal)] hover:text-white">Turn it on</button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span id="lab-style-label" class="text-xs text-slate-400">Style</span>
                    <div class="axis-segmented w-[240px]" role="group" aria-labelledby="lab-style-label">
                        @foreach (['crystal' => 'Crystal', 'mechanical' => 'Clicky', 'minimal' => 'Soft'] as $presetKey => $presetLabel)
                            <button type="button" onclick="SoundFX.setPreset('{{ $presetKey }}')" data-preset-btn="{{ $presetKey }}" data-no-click-sfx="true"
                                    class="axis-segmented__btn" aria-pressed="false">{{ $presetLabel }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 overflow-y-auto pr-1 min-h-0">
                @foreach ($labSounds as [$soundLabel, $soundHint, $soundCall, $soundIcon, $soundTone])
                    <button type="button" data-no-click-sfx="true" onclick="auditionSound(this, () => {{ $soundCall }})"
                            @class(['axis-sound-card', 'axis-sound-card--'.$soundTone => $soundTone])>
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-white">{{ $soundLabel }}</span>
                            <i class="fa-solid {{ $soundIcon }} axis-sound-card__icon" aria-hidden="true"></i>
                        </span>
                        <span class="block text-xs text-slate-400 mt-1">{{ $soundHint }}</span>
                    </button>
                @endforeach
            </div>

            <div class="flex items-center justify-between gap-3 pt-4 mt-4 border-t border-white/[0.08]">
                <p class="text-xs text-slate-400">Tip: turn sounds off any time from the sound button in the top bar.</p>
                <button type="button" onclick="closeModal('modal-sound-lab')" data-no-click-sfx="true" class="btn-primary-glass h-10 px-5 text-xs font-semibold shrink-0">Done</button>
            </div>
        </div>
    </div>
