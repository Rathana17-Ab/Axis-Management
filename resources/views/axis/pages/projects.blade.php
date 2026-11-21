                <!-- ===================================================================== -->
                <!-- PROJECTS (`projects` - `admin/project`): top level of Project → Module → Task -->
                <!-- ===================================================================== -->
                <div id="page-projects" @class(['app-page', 'active-page' => $initialPage === 'projects', 'space-y-5'])>

                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pt-1">
                        <div>
                            <h1 class="text-xl font-bold text-white tracking-tight">Projects</h1>
                            <p class="text-xs text-slate-300/90 mt-1 max-w-3xl">Each project groups its modules. Open one to see its modules, then a module to see its tasks.</p>
                        </div>
                        <button type="button" onclick="openModal('drawer-project-create')" class="btn-primary-glass px-4 py-2 text-xs font-semibold flex items-center gap-2 shrink-0 self-start">
                            <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
                            <span>New project</span>
                        </button>
                    </div>

                    <!-- KPI strip (x-axis.kpi-card), filled by renderAxisProjectKpis() -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="axis-project-kpi-strip">
                        <x-axis.kpi-card key="ap-total" accent="cyan" icon="fa-folder-tree" status="All" title="Projects" />
                        <x-axis.kpi-card key="ap-modules" accent="emerald" icon="fa-cubes" status="Total" title="Modules" />
                        <x-axis.kpi-card key="ap-avg" accent="violet" icon="fa-chart-line" status="Avg" title="Average progress" />
                        <x-axis.kpi-card key="ap-next" accent="amber" icon="fa-calendar-day" status="Next" title="Next deadline" />
                    </div>

                    <!-- Filter command bar -->
                    <div class="sticky-filter-dock axis-command-bar flex flex-wrap items-center gap-2">
                        <label class="axis-field-shell axis-command-search flex items-center">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="ap-flt-search" oninput="renderAxisProjectsTable()" placeholder="Search by project name or description…" aria-label="Search projects">
                        </label>
                        <select id="ap-flt-status" onchange="renderAxisProjectsTable()" class="ent-input axis-select-control" aria-label="Status">
                            <option value="all">All statuses</option>
                            @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                            @endforeach
                        </select>
                        <select id="ap-flt-sort" onchange="setAxisProjectSortFromSelect(this.value)" class="ent-input axis-select-control" aria-label="Sort by">
                            <option value="" hidden>Custom order</option>
                            <option value="name:asc" selected>Name (A–Z)</option>
                            <option value="status:asc">Status</option>
                            <option value="progress:desc">Progress (highest)</option>
                            <option value="progress:asc">Progress (lowest)</option>
                            <option value="deadline:asc">Deadline (soonest)</option>
                            <option value="issues:desc">Open issues (most)</option>
                            <option value="modules:desc">Modules (most)</option>
                        </select>
                        <div class="axis-command-actions flex items-center gap-1.5">
                            @include('axis.partials.list-view-switch', ['list' => 'projects'])
                            <button type="button" onclick="resetAxisProjectFilters()" class="btn-glass text-xs font-semibold flex items-center gap-1.5 shrink-0" title="Reset filters">
                                <i class="fa-solid fa-rotate-left text-[10px]" aria-hidden="true"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>

                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                    <i class="fa-solid fa-folder-tree text-xs" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-white">Project list</h2>
                                    <p class="text-xs text-slate-400">Click a project to see its modules.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="exportAxisProjectsCSV()" class="btn-glass px-3 py-1.5 text-xs font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-file-export text-sky-300 text-[10px]" aria-hidden="true"></i>
                                    <span>Export CSV</span>
                                </button>
                            </div>
                        </div>

                        <!-- Wide table: scrolls sideways on smaller screens; project name and actions stay pinned -->
                        <div id="ap-view-table" class="overflow-x-auto axis-hscroll">
                            <table class="w-full min-w-[1180px] ent-table axis-wide-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        @foreach ([
                                            ['name', 'Project', 'axis-sticky-left pl-5 pr-3'],
                                            ['status', 'Status', 'px-3'],
                                            ['progress', 'Progress', 'px-3'],
                                            ['modules', 'Modules', 'px-3'],
                                            ['tasks', 'Tasks done', 'px-3'],
                                            [null, 'People', 'px-3'],
                                            ['issues', 'Open issues', 'px-3'],
                                            ['deadline', 'Timeline', 'px-3'],
                                        ] as [$sortKey, $label, $classes])
                                            <th class="py-3 {{ $classes }}" @if ($sortKey) aria-sort="none" data-ap-sort="{{ $sortKey }}" @endif>
                                                @if ($sortKey)
                                                    <button type="button" onclick="sortAxisProjects('{{ $sortKey }}')" class="axis-sort-btn">
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
                                <tbody id="axis-projects-tbody"></tbody>
                            </table>
                        </div>
                        <!-- Cards and Board views, filled by renderAxisProjectsTable() -->
                        <div id="ap-view-cards" class="hidden p-4 grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-3"></div>
                        <div id="ap-view-board" class="hidden p-4 grid grid-cols-1 lg:grid-cols-3 gap-3"></div>

                        <div class="px-5 py-3 border-t border-sky-400/15 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400 bg-white/[0.04]">
                            <span id="axis-projects-showing-text">Showing {{ $axisProjects->count() }} of {{ $axisProjects->count() }} projects</span>
                            <span id="ap-view-hint" class="hidden sm:inline text-slate-500">Scroll sideways to see every column</span>
                        </div>
                    </div>
                </div>
