            <!-- ===================================================================== -->
            <!-- TOP HEADER — flat Axis Sales top bar (.axis-topbar*, axis-sales-theme)  -->
            <!-- Items collapse by the bar's own width (container queries, .tb-* in     -->
            <!-- axis-sales-theme.css), so the pinned sidebar never makes them overlap. -->
            <!-- Element ids are driven by updateDynamicHeader(), the timer loop,      -->
            <!-- and the live save-status listener.                                    -->
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

                    <!-- Search · running timer · save status -->
                    <div class="axis-topbar__middle flex items-center gap-2">
                        <button type="button" id="header-global-search" onclick="openCommandPalette()"
                            class="axis-topbar-pill axis-topbar-pill--search tb-search-full"
                            aria-haspopup="dialog" aria-controls="axis-command-palette" aria-keyshortcuts="Meta+K Control+K">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <span class="flex-1 text-left text-slate-400 truncate">Search anything…</span>
                            <kbd id="header-search-shortcut" class="text-xs font-mono text-sky-300/80 bg-white/[0.06] border border-sky-400/20 px-1.5 py-0.5 rounded shrink-0">⌘K</kbd>
                        </button>
                        <button type="button" onclick="openCommandPalette()" class="axis-topbar-icon-btn tb-search-icon" aria-label="Search" aria-haspopup="dialog" aria-controls="axis-command-palette">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>

                        <div id="header-timer-pill" class="hidden axis-topbar-pill axis-topbar-pill--timer tb-show-xs cursor-pointer shrink-0" onclick="openProjectTaskList(activeProjectId)" title="Jump to the running task">
                            <span class="w-2 h-2 rounded-full bg-sky-400 shadow-[0_0_0_3px_rgba(56,189,248,0.2)] shrink-0" aria-hidden="true"></span>
                            <span id="header-timer-label" class="font-mono font-semibold truncate max-w-[110px]">Timer</span>
                        </div>

                        <span id="header-sync-pill" class="axis-topbar-pill axis-topbar-pill--status tb-show-md shrink-0" data-state="idle" role="status" aria-live="polite" title="Up to date">
                            <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
                            <span id="header-sync-label" class="tb-show-xl">Up to date</span>
                        </span>

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
