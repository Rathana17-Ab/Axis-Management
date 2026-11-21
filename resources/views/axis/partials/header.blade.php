            <!-- ===================================================================== -->
            <!-- TOP HEADER — flat Axis Sales top bar (.axis-topbar*, axis-sales-theme)  -->
            <!-- Items collapse by the bar's own width (container queries, .tb-* in     -->
            <!-- axis-sales-theme.css), so the pinned sidebar never makes them overlap. -->
            <!-- Element ids are driven by updateDynamicHeader(), the timer loop,      -->
            <!-- SoundFX and the live save-status listener.                            -->
            <!-- ===================================================================== -->
            <div class="axis-topbar-wrap shrink-0 z-40 relative select-none">
                <header class="axis-topbar">

                    <!-- Opens the sidebar on screens narrower than 1024px -->
                    <button type="button" id="sb-open-btn" onclick="toggleSidebar(true)" class="axis-topbar-icon-btn axis-sidebar-toggle" aria-label="Open the menu" aria-controls="eot-sidebar" aria-expanded="false">
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>

                    <!-- Page title + breadcrumb -->
                    <div class="axis-topbar__heading">
                        <div class="axis-topbar__subtitle flex items-center gap-1.5 min-w-0">
                            <button type="button" onclick="switchPage('dashboard')" class="axis-topbar__crumb tb-show-sm shrink-0">Axis Management</button>
                            <span class="text-white/40 tb-show-sm shrink-0" aria-hidden="true">·</span>
                            <button type="button" id="header-section-label" onclick="openHeaderSection()" class="axis-topbar__crumb truncate">Project Management</button>
                        </div>
                        <div class="flex items-center gap-2 min-w-0">
                            <h2 id="header-page-title" class="axis-topbar__title truncate">Projects</h2>
                            <span id="header-page-badge" class="tb-show-lg inline-flex items-center px-2 h-5 rounded-full text-xs font-medium bg-sky-500/15 text-sky-300 border border-sky-400/25 shrink-0 whitespace-nowrap">{{ $axisProjects->count() }} projects</span>
                        </div>
                    </div>

                    <span class="axis-topbar__divider tb-show-lg" aria-hidden="true"></span>

                    <!-- Search · running timer · save status · sound -->
                    <div class="axis-topbar__middle flex items-center gap-2">
                        <button type="button" id="header-global-search" onclick="openCommandPalette()" data-no-click-sfx="true"
                            class="axis-topbar-pill axis-topbar-pill--search tb-search-full"
                            aria-haspopup="dialog" aria-controls="axis-command-palette" aria-keyshortcuts="Meta+K Control+K">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <span class="flex-1 text-left text-slate-400 truncate">Search anything…</span>
                            <kbd id="header-search-shortcut" class="text-xs font-mono text-sky-300/80 bg-white/[0.06] border border-sky-400/20 px-1.5 py-0.5 rounded shrink-0">⌘K</kbd>
                        </button>
                        <button type="button" onclick="openCommandPalette()" data-no-click-sfx="true" class="axis-topbar-icon-btn tb-search-icon" aria-label="Search" aria-haspopup="dialog" aria-controls="axis-command-palette">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>

                        <div id="header-timer-pill" class="hidden axis-topbar-pill axis-topbar-pill--timer tb-show-xs cursor-pointer shrink-0" onclick="openProjectTaskList(activeProjectId)" title="Jump to the running task">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.2)] shrink-0" aria-hidden="true"></span>
                            <span id="header-timer-label" class="font-mono font-semibold truncate max-w-[110px]">Timer</span>
                        </div>

                        <span id="header-sync-pill" class="axis-topbar-pill axis-topbar-pill--status tb-show-md shrink-0" data-state="idle" role="status" aria-live="polite" title="Up to date">
                            <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                            <span id="header-sync-label" class="tb-show-xl">Up to date</span>
                        </span>

                        <div class="relative shrink-0 tb-show-md" id="audio-control-wrapper">
                            <button type="button" id="audio-menu-btn" onclick="toggleAudioPopover(event)" data-no-click-sfx="true" data-muted="false"
                                class="axis-topbar-pill" aria-haspopup="dialog" aria-expanded="false" aria-controls="audio-popover" title="Sound settings">
                                <span id="sfx-icon-wrap" class="flex items-center justify-center shrink-0" aria-hidden="true">
                                    <svg class="sfx-icon-on w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                    </svg>
                                    <svg class="sfx-icon-off w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                                    </svg>
                                </span>
                                <span id="sound-eq" class="tb-show-lg flex items-end gap-0.5 h-3 px-0.5 shrink-0" aria-hidden="true">
                                    <span class="sfx-eq-bar"></span>
                                    <span class="sfx-eq-bar"></span>
                                    <span class="sfx-eq-bar"></span>
                                    <span class="sfx-eq-bar"></span>
                                    <span class="sfx-eq-bar"></span>
                                </span>
                                <span id="sfx-status-text" class="tb-show-2xl">Sound on</span>
                                <svg id="audio-popover-chevron" class="axis-topbar-pill__chevron w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Sound settings popover (state is synced by SoundFX.syncUI and saved per browser) -->
                            <div id="audio-popover" onclick="event.stopPropagation()" role="dialog" aria-label="Sound settings"
                                 class="hidden absolute right-0 top-full mt-2 w-72 axis-sound-popover z-50 select-none">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-white">Sound effects</div>
                                        <div id="popover-preset-subtitle" class="text-xs text-slate-400">Crystal</div>
                                    </div>
                                    <button type="button" id="popover-mute-switch" role="switch" aria-checked="true" aria-label="Sound effects"
                                            onclick="SoundFX.toggleMute()" data-no-click-sfx="true" class="axis-switch">
                                        <span class="axis-switch__thumb"></span>
                                    </button>
                                </div>

                                <div class="axis-sound-popover__body space-y-3.5">
                                    <div>
                                        <label for="sfx-preset-select" class="block text-xs font-semibold text-slate-400 mb-1.5">Sound style</label>
                                        <select id="sfx-preset-select" onchange="SoundFX.setPreset(this.value)" data-no-click-sfx="true" class="axis-input w-full h-10 px-3 text-sm cursor-pointer">
                                            <option value="crystal">Crystal</option>
                                            <option value="mechanical">Clicky</option>
                                            <option value="minimal">Soft</option>
                                        </select>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <span id="sfx-vol-label" class="text-xs font-semibold text-slate-400">Volume</span>
                                            <span id="sfx-vol-display" class="text-xs font-mono text-slate-300">60%</span>
                                        </div>
                                        <div class="axis-segmented" role="group" aria-labelledby="sfx-vol-label">
                                            @foreach ([['0.3', 'Low'], ['0.6', 'Medium'], ['1', 'High']] as [$level, $levelLabel])
                                                <button type="button" onclick="SoundFX.setVolume({{ $level }})" data-vol-btn="{{ $level }}" data-no-click-sfx="true"
                                                        class="vol-seg-btn axis-segmented__btn" aria-pressed="false">{{ $levelLabel }}</button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div id="sfx-tick-title" class="text-xs font-medium text-slate-200">Timer ticking</div>
                                            <div class="text-xs text-slate-500">A soft tick each second while a timer runs</div>
                                        </div>
                                        <button type="button" id="sfx-tick-btn" role="switch" aria-checked="false" aria-labelledby="sfx-tick-title"
                                                onclick="SoundFX.toggleClockTick()" data-no-click-sfx="true" class="axis-switch shrink-0">
                                            <span class="axis-switch__thumb"></span>
                                        </button>
                                    </div>
                                </div>

                                <button type="button" onclick="closeAudioPopover(); openModal('modal-sound-lab');" data-no-click-sfx="true"
                                        class="btn-glass w-full h-10 mt-4 text-xs font-semibold flex items-center justify-center gap-2">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                    <span>Try all the sounds</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0">
                        <select id="header-module-select" onchange="if(this.value) openProjectTaskList(parseInt(this.value, 10))" class="ent-input tb-show-3xl h-11 px-3.5 w-48 text-sm shrink-0" aria-label="Jump to a module">
                            <option value="">Jump to a module…</option>
                        </select>

                        <button type="button" id="header-create-btn" onclick="triggerHeaderContextCreate(this)" class="axis-topbar-create btn-primary-glass" title="Create" aria-label="Create">
                            <svg id="header-create-icon" class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span id="header-create-label" class="tb-show-xl">New project</span>
                        </button>

                        <span class="axis-topbar__divider tb-show-md" aria-hidden="true"></span>

                        <button type="button" class="axis-topbar-icon-btn tb-show-sm" onclick="refreshWorkspaceData(this)" title="Reload the latest data" aria-label="Reload the latest data">
                            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                        </button>

                        <button type="button" onclick="switchPage('reports')" class="axis-topbar-icon-btn tb-show-xs" title="Reports" aria-label="Reports">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                        </button>

                        <button type="button" onclick="openModal('login-modal')" class="axis-topbar-pill axis-topbar-profile shrink-0" title="Switch user" aria-label="Switch user">
                            <span id="top-avatar" class="w-7 h-7 rounded-[10px] bg-gradient-to-br from-[#2F6FD6] to-[#2EC6E8] text-white font-bold text-xs flex items-center justify-center shrink-0">
                                {{ $currentUser?->initials }}
                            </span>
                            <span id="top-username" class="tb-show-2xl font-medium whitespace-nowrap">{{ $currentUser?->name }}</span>
                            <svg class="axis-topbar-pill__chevron tb-show-xs w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </header>
            </div>
