                <!-- ===================================================================== -->
                <!-- MODULE LIST (`modules` - `admin/modules`)                          -->
                <!-- ===================================================================== -->
                <div id="page-modules" @class(['app-page', 'active-page' => $initialPage === 'modules', 'space-y-5'])>

                    <!-- Page heading & metadata pills -->
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pt-1">
                        <div class="space-y-3">
                            <div>
                                <h1 class="text-xl font-bold text-white tracking-tight">Modules</h1>
                                <p class="text-xs text-slate-300/90 mt-1 max-w-3xl">The modules inside each project: who's on them, how far along they are, and what's due next.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="axis-meta-pill">
                                    <svg class="w-3.5 h-3.5 text-sky-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                                    <span id="meta-active-count">{{ $projects->where('status', \App\Enums\WorkStatus::Processing)->count() }} active</span>
                                </span>
                                <span class="axis-meta-pill">
                                    <svg class="w-3.5 h-3.5 text-sky-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                                    <span id="meta-people-count">— people</span>
                                </span>
                                <span class="axis-meta-pill">
                                    <svg class="w-3.5 h-3.5 text-sky-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    {{-- Server time is UTC; renderContextBar() rewrites this in the viewer's own timezone --}}
                                    <span>Updated <time id="meta-updated-at" datetime="{{ now()->toIso8601String() }}">{{ now()->format('j M, H:i') }}</time></span>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 self-start">
                            <button type="button" onclick="openModal('modal-add-project')" class="btn-primary-glass px-4 py-2 text-xs font-semibold flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                                <span>New module</span>
                            </button>
                        </div>
                    </div>

                    <!-- Executive KPI strip (resources/views/components/axis/kpi-card.blade.php), filled by renderProjectKpis() -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="project-kpi-strip">
                        <x-axis.kpi-card key="proj-active" accent="cyan" icon="fa-briefcase" status="Live" :live="true" title="Active modules" />
                        <x-axis.kpi-card key="proj-total" accent="emerald" icon="fa-eye" status="Active" title="Modules you can see" />
                        <x-axis.kpi-card key="proj-avg" accent="violet" icon="fa-chart-line" status="Avg" title="Average progress" />
                        <x-axis.kpi-card key="proj-squad" accent="amber" icon="fa-users" status="Healthy" title="People on modules" />
                    </div>

                    <!-- AXIS SALES ATTENTION BANNER (`No visible segment requires attention` style) -->
                    <div id="axis-attention-banner" class="ent-card px-4 py-3 flex items-center justify-between gap-3" role="status" aria-live="polite">
                        <div class="flex items-center gap-3">
                            <div id="axis-attention-icon" class="w-8 h-8 rounded-lg bg-amber-500/15 border border-amber-400/30 flex items-center justify-center text-amber-300 shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                            <div>
                                <div id="axis-attention-title" class="text-xs font-semibold text-white">Checking issues…</div>
                                <div id="axis-attention-desc" class="text-xs text-slate-400"></div>
                            </div>
                        </div>
                        <button type="button" onclick="document.getElementById('axis-attention-banner').classList.add('hidden'); showToast('Banner dismissed for session');" class="btn-glass px-3.5 py-1.5 text-xs font-semibold shrink-0">
                            Dismiss
                        </button>
                    </div>

                    <!-- Filter command bar: one row of search, filters and actions (Axis Sales pattern) -->
                    <div id="sticky-filter-bar" class="sticky-filter-dock axis-command-bar flex flex-wrap items-center gap-2">
                        <label class="axis-field-shell axis-command-search flex items-center">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="flt-search"
                                oninput="renderProjectsTable(false)"
                                onkeydown="if(event.key==='Enter') applyAndNavigateFilters()"
                                placeholder="Search by module, code or lead…"
                                aria-label="Search modules">
                            <button type="button" id="flt-clear-search-btn" onclick="clearSingleFilter('search')" class="hidden axis-field-shell__clear" aria-label="Clear search">&times;</button>
                        </label>
                        <select id="flt-project" onchange="applyAndNavigateFilters()" class="ent-input axis-select-control" aria-label="Project">
                            <option value="all">All projects</option>
                            @foreach ($axisProjects as $axisProject)
                                <option value="{{ $axisProject->id }}">{{ $axisProject->name }}</option>
                            @endforeach
                        </select>
                        <select id="flt-product" onchange="applyAndNavigateFilters()" class="ent-input axis-select-control" aria-label="Product">
                            <option value="all">All products</option>
                            <option value="Axis Core Suite">Axis Core Suite</option>
                            <option value="Axis Hospitality PMS">Axis Hospitality PMS</option>
                            <option value="Axis Commercial POS">Axis Commercial POS</option>
                            <option value="Axis Enterprise Finance">Axis Enterprise Finance</option>
                        </select>
                        <select id="flt-status" onchange="applyAndNavigateFilters()" class="ent-input axis-select-control" aria-label="Status">
                            <option value="all">All statuses</option>
@foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                            @endforeach
                        </select>
                        <select id="flt-sort" onchange="setModuleSortFromSelect(this.value)" class="ent-input axis-select-control" aria-label="Sort by">
                            <option value="custom:asc" selected>Custom order</option>
                            <option value="name:asc">Name (A–Z)</option>
                            <option value="status:asc">Status</option>
                            <option value="progress:desc">Progress (highest)</option>
                            <option value="progress:asc">Progress (lowest)</option>
                            <option value="deadline:asc">Deadline (soonest)</option>
                            <option value="issues:desc">Open issues (most)</option>
                            <option value="lead:asc">Lead (A–Z)</option>
                        </select>
                        <div class="axis-command-actions flex items-center gap-1.5">
                            @include('axis.partials.list-view-switch', ['list' => 'modules'])
                            <button type="button" onclick="resetProjectFilters()" class="btn-glass text-xs font-semibold flex items-center gap-1.5 shrink-0" title="Reset all filters">
                                <i class="fa-solid fa-rotate-left text-[10px]" aria-hidden="true"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>

                    <!-- Active filters: removable chips + match count -->
                    <div id="active-filter-chips-bar" class="hidden axis-filter-strip flex-wrap items-center gap-1.5">
                        <div class="flex flex-wrap items-center gap-1.5" id="active-filter-chips-list"></div>
                        <span id="filter-match-badge" class="axis-filter-count">Showing {{ $projects->count() }} of {{ $projects->count() }} modules</span>
                        <button type="button" onclick="resetProjectFilters()" class="axis-filter-clear">Clear all</button>
                    </div>

                    <!-- MODULE LIST TABLE CARD -->
                    <div id="project-list-results-card" class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                    <i class="fa-solid fa-table-list text-xs"></i>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-white">Module list</h2>
                                    <p class="text-xs text-slate-400">Drag to reorder · click a module to see its tasks.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="exportProjectsCSV()" class="btn-glass px-3 py-1.5 text-xs font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-file-export text-sky-300 text-[10px]"></i>
                                    <span>Export CSV</span>
                                </button>
                                <button type="button" onclick="openModal('modal-add-project')" class="btn-primary-glass px-3.5 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>Add module</span>
                                </button>
                            </div>
                        </div>

                        <!-- Wide table: scrolls sideways on smaller screens; module name and actions stay pinned -->
                        <div id="mod-view-table" class="overflow-x-auto axis-hscroll">
                            <table class="w-full min-w-[1320px] ent-table axis-wide-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        @foreach ([
                                            ['name', 'Module', 'axis-sticky-left pl-5 pr-3'],
                                            ['status', 'Status', 'px-3'],
                                            ['progress', 'Progress', 'px-3'],
                                            ['lead', 'Lead', 'px-3'],
                                            [null, 'Team', 'px-3'],
                                            ['tasks', 'Tasks done', 'px-3'],
                                            ['issues', 'Open issues', 'px-3'],
                                            ['deadline', 'Timeline', 'px-3'],
                                        ] as [$sortKey, $label, $classes])
                                            <th class="py-3 {{ $classes }}" @if ($sortKey) aria-sort="none" data-mod-sort="{{ $sortKey }}" @endif>
                                                @if ($sortKey)
                                                    <button type="button" onclick="sortModules('{{ $sortKey }}')" class="axis-sort-btn">
                                                        <span>{{ $label }}</span>
                                                        <i class="fa-solid fa-sort" aria-hidden="true"></i>
                                                    </button>
                                                @else
                                                    {{ $label }}
                                                @endif
                                            </th>
                                        @endforeach
                                        <th class="py-3 pl-3 pr-5 axis-sticky-right text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="projects-tbody"></tbody>
                            </table>
                        </div>
                        <!-- Cards and Board views, filled by renderProjectsTable() -->
                        <div id="mod-view-cards" class="hidden p-4 grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-3"></div>
                        <div id="mod-view-board" class="hidden p-4 grid grid-cols-1 lg:grid-cols-3 gap-3"></div>

                        <div class="px-5 py-3 border-t border-sky-400/15 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400 bg-white/[0.04]">
                            <span id="projects-showing-text">Showing {{ $projects->count() }} of {{ $projects->count() }} modules</span>
                            <div class="flex items-center gap-1">
                                <span class="px-2.5 py-1 rounded-lg bg-sky-500/20 border border-sky-400/35 text-sky-200 text-xs font-semibold">Page 1 of 1</span>
                            </div>
                        </div>
                    </div>
                </div>
