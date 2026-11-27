        {{-- Left sidebar: one menu item per page. Desktop: always visible. Below 1024px: slides in from the header's menu button. --}}
        @php
            /** @var list<array{0: string, 1: string, 2: string, 3: string}> $sidebarPages [page id, label, onclick, icon svg paths] */
            $sidebarPages = [
                ['dashboard', 'Dashboard', "switchPage('dashboard')", '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>'],
                ['projects', 'Projects', "switchPage('projects')", '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>'],
                ['modules', 'Modules', "switchPage('modules')", '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/>'],
                ['task-detail', 'Tasks', 'openProjectTaskList(activeProjectId)', '<rect width="6" height="6" x="3" y="5" rx="1"/><path d="m3 17 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>'],
                ['reports', 'Reports', "switchPage('reports')", '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>'],
                ['team', 'Team', "switchPage('team')", '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ];

        @endphp
        <aside id="eot-sidebar" class="axis-sidebar select-none" aria-label="Main menu"
               onmouseenter="hoverSidebar(true)" onmouseleave="hoverSidebar(false)">
            <div class="axis-sidebar__brand">
                <button type="button" onclick="switchPage('dashboard')" class="axis-sidebar__logo" aria-label="Axis Management — dashboard">
                    <img src="{{ asset('images/brand/axis-symbol-white.png') }}?v={{ filemtime(public_path('images/brand/axis-symbol-white.png')) }}" alt="" aria-hidden="true" width="34" height="34" class="block w-[34px] h-[34px] object-contain">
                </button>
                <div class="axis-sidebar__text min-w-0 flex-1">
                    <div class="text-[16px] font-semibold text-white leading-tight whitespace-nowrap">Axis Management</div>
                    <div class="text-xs text-slate-400 mt-0.5 truncate">Project hub</div>
                </div>
                <button type="button" id="sb-pin-btn" onclick="setSidebarPinned(!isSidebarPinned())" class="axis-sidebar__pin axis-sidebar__text"
                        aria-pressed="true" aria-label="Collapse the menu to icons" title="Collapse the menu to icons">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="17" x2="12" y2="22"/>
                        <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>
                    </svg>
                </button>
                <button type="button" onclick="toggleSidebar(false)" class="axis-sidebar__close" aria-label="Close the menu">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="axis-sidebar__nav" aria-label="Pages">
                @foreach ($sidebarPages as [$pageKey, $pageLabel, $pageAction, $pageIcon])
                    <button type="button" onclick="{{ $pageAction }}" id="nav-{{ $pageKey }}" class="sidebar-link axis-sidebar__item" aria-label="{{ $pageLabel }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $pageIcon !!}</svg>
                        <span class="axis-sidebar__text truncate">{{ $pageLabel }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="axis-sidebar__footer">
                <div class="relative">
                    <button type="button" id="sidebar-profile-btn" onclick="toggleSidebarProfileMenu()" class="axis-sidebar__profile" aria-label="View profile — signed in as {{ $currentUser?->name }}" aria-haspopup="menu" aria-expanded="false" aria-controls="sidebar-profile-menu">
                        <span class="relative shrink-0">
                            <span id="sb-avatar" class="axis-sidebar__avatar">{{ $currentUser?->initials }}</span>
                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-400 ring-2 ring-[#0B2248]" aria-hidden="true"></span>
                        </span>
                        <span class="axis-sidebar__text min-w-0 flex-1 text-left">
                            <span id="sb-name" class="block text-sm font-semibold text-white truncate">{{ $currentUser?->name }}</span>
                            <span id="sb-role" class="block text-xs text-slate-400 truncate">{{ $currentUser?->job_title }}</span>
                        </span>
                        <i class="axis-sidebar__text fa-solid fa-chevron-down text-[11px] text-slate-400 shrink-0" aria-hidden="true"></i>
                    </button>

                    <div id="sidebar-profile-menu" style="bottom: calc(100% + 0.5rem);" class="hidden ent-card absolute right-0 z-40 w-full overflow-hidden" role="menu" aria-labelledby="sidebar-profile-btn">
                        <button type="button" role="menuitem" data-profile-menu-item onclick="switchPage('profile')" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-semibold text-slate-100 transition-colors hover:bg-white/10 hover:text-white">
                            <i class="fa-regular fa-user text-[11px] text-slate-300" aria-hidden="true"></i>
                            <span>Profile</span>
                        </button>
                        <div class="border-t border-white/10" aria-hidden="true"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" id="sidebar-profile-sign-out" role="menuitem" data-profile-menu-item class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-semibold text-rose-300 transition-colors hover:bg-white/10">
                                <i class="fa-solid fa-right-from-bracket text-[11px]" aria-hidden="true"></i>
                                <span>Sign out</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>
        <div id="axis-side-backdrop" class="axis-side-backdrop hidden" onclick="toggleSidebar(false)" aria-hidden="true"></div>
