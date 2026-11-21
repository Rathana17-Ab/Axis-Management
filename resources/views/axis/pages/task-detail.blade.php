                <!-- ===================================================================== -->
                <!-- PAGE 2: TASK LIST DETAIL (`task-detail` - `admin/project-task-list`)  -->
                <!-- ===================================================================== -->
                <div id="page-task-detail" @class(['app-page', 'active-page' => $initialPage === 'task-detail', 'space-y-5'])>

                    <!-- 1. MODULE HEADER (plain, no card): back link, name + status, key facts, actions (filled by renderTaskDetailPage) -->
                    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
                        <div class="flex-1 min-w-[min(100%,420px)]">
                            <button type="button" onclick="openModulesOfProject(projects.find(x => x.id === activeProjectId)?.projectId)" class="axis-hero-back">
                                <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                <span id="td-back-label">All modules</span>
                            </button>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2">
                                {{-- The title is the module switcher (toggleModuleSwitcher / renderModuleSwitcher) --}}
                                <div class="relative z-40">
                                    <h1 class="text-2xl font-bold text-white tracking-tight">
                                        <button type="button" id="td-switch-btn" onclick="toggleModuleSwitcher()" class="axis-module-switch"
                                            aria-haspopup="listbox" aria-expanded="false" aria-controls="td-switch-menu" title="Switch module">
                                            <span id="td-title">Axis Core System</span>
                                            <i class="fa-solid fa-chevron-down axis-module-switch__chevron" aria-hidden="true"></i>
                                        </button>
                                    </h1>
                                    <div id="td-switch-menu" class="axis-module-menu hidden" onkeydown="handleModuleSwitcherKey(event)">
                                        <input type="search" id="td-switch-search" class="axis-input axis-module-menu__search hidden" placeholder="Find a module…"
                                            aria-label="Find a module" oninput="renderModuleSwitcher(this.value)">
                                        <div id="td-switch-list" role="listbox" aria-label="Modules"></div>
                                    </div>
                                </div>
                                <span id="td-code-badge" class="axis-hero-code">PROJ-1888</span>
                                <span id="td-status-badge" class="px-2.5 py-0.5 rounded-full border text-xs font-semibold">—</span>
                            </div>
                            <p class="text-sm text-slate-400 mt-1">Tasks, who's on them, time tracked, and any issues along the way.</p>

                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" onclick="openProjectEdit(activeProjectId)" class="btn-glass axis-hero-action" title="Edit this module's details and team">
                                <i class="fa-solid fa-pen-to-square text-xs" aria-hidden="true"></i>
                                <span>Edit</span>
                            </button>
                            <button type="button" onclick="exportActiveTasksCSV()" class="btn-glass axis-hero-action">
                                <i class="fa-solid fa-download text-xs" aria-hidden="true"></i>
                                <span>Export</span>
                            </button>
                            <button type="button" onclick="openDevIssueDrawer()" class="btn-glass axis-hero-action">
                                <i class="fa-solid fa-bug text-xs" aria-hidden="true"></i>
                                <span>Issues</span>
                            </button>
                            <button type="button" onclick="openModal('modal-create-task')" class="btn-primary-glass axis-hero-action">
                                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                                <span>New task</span>
                            </button>
                        </div>
                        <dl class="axis-hero-meta">
                            <div>
                                <dt>Customer</dt>
                                <dd id="td-company">—</dd>
                            </div>
                            <div>
                                <dt>Product</dt>
                                <dd id="td-domain-pill">—</dd>
                            </div>
                            <div>
                                <dt>Lead</dt>
                                <dd id="td-mgr-pill">—</dd>
                            </div>
                            <div>
                                <dt>Deadline</dt>
                                <dd><span id="td-dl-pill">—</span> <span id="td-dl-left" class="text-xs"></span></dd>
                            </div>
                        </dl>
                    </div>

                    <!-- 2. Task KPI strip (same axis kpi-card component as the Projects page), filled by refreshDetailSummaryCards() -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="task-kpi-strip">
                        <x-axis.kpi-card key="td-progress" accent="cyan" icon="fa-chart-line" status="In progress" title="Overall progress" />
                        <x-axis.kpi-card key="td-done" accent="emerald" icon="fa-circle-check" status="0% done" title="Tasks done" />
                        <x-axis.kpi-card key="td-time" accent="violet" icon="fa-stopwatch" status="Paused" title="Time logged" />
                        <x-axis.kpi-card key="td-issues" accent="amber" icon="fa-bug" status="All fixed" title="Issues & fixes" />
                    </div>

                    <!-- 4. Task filter command bar: one row of search, filters and actions (Axis Sales pattern) -->
                    <div id="sticky-task-filter-bar" class="sticky-filter-dock axis-command-bar flex flex-wrap items-center gap-2">
                        <label class="axis-field-shell axis-command-search flex items-center">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="text" id="task-inline-search"
                                oninput="renderTasksTableRows()"
                                placeholder="Search tasks or people…"
                                aria-label="Search tasks">
                            <button type="button" id="td-clear-search-btn" onclick="clearSingleTaskFilter('search')" class="hidden axis-field-shell__clear" aria-label="Clear search">&times;</button>
                        </label>
                        <select id="td-flt-issue" onchange="setTaskIssueFilter(this.value)" class="ent-input axis-select-control" aria-label="Show">
                            <option value="all" id="cnt-tasks-all">All tasks</option>
                            <option value="running" id="cnt-tasks-running">Timer running</option>
                            <option value="open" id="cnt-tasks-open">Open issues</option>
                            <option value="resolved" id="cnt-tasks-resolved">Fixed</option>
                        </select>
                        <select id="td-flt-handler" onchange="renderTasksTableRows(true)" class="ent-input axis-select-control" aria-label="Assignee">
                            <option value="all">Everyone</option>
                        </select>
                        <select id="td-flt-status" onchange="renderTasksTableRows(true)" class="ent-input axis-select-control" aria-label="Status">
                            <option value="all">All statuses</option>
@foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                            @endforeach
                        </select>
                        <select id="td-flt-priority" onchange="renderTasksTableRows(true)" class="ent-input axis-select-control" aria-label="Priority">
                            <option value="all">All priorities</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                        <select id="td-flt-sort" onchange="renderTasksTableRows(true)" class="ent-input axis-select-control" aria-label="Sort by">
                            <option value="default">Default order</option>
                            <option value="progress-desc">Progress (high → low)</option>
                            <option value="progress-asc">Progress (low → high)</option>
                            <option value="hours-desc">Time logged (most first)</option>
                            <option value="priority">Priority (high → low)</option>
                            <option value="deadline-asc">Deadline (soonest first)</option>
                            <option value="deadline-desc">Deadline (latest first)</option>
                        </select>
                        <div class="axis-command-actions flex items-center gap-1.5">
                            <div class="axis-view-switch inline-flex items-center" role="group" aria-label="View">
                                <button type="button" id="td-view-table-btn" onclick="setTaskDetailViewMode('table')" class="axis-view-switch__btn is-active" title="Table view" aria-label="Table view">
                                    <i class="fa-solid fa-table-list"></i>
                                </button>
                                <button type="button" id="td-view-cards-btn" onclick="setTaskDetailViewMode('cards')" class="axis-view-switch__btn" title="Card view" aria-label="Card view">
                                    <i class="fa-solid fa-grip"></i>
                                </button>
                            </div>
                            <button type="button" onclick="resetTaskDetailFilters()" class="btn-glass text-xs font-semibold flex items-center gap-1.5 shrink-0" title="Reset filters">
                                <i class="fa-solid fa-rotate-left text-[10px]" aria-hidden="true"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>

                    <!-- Active task filters: removable chips -->
                    <div id="td-active-chips-bar" class="hidden axis-filter-strip flex-wrap items-center gap-1.5">
                        <div id="td-active-chips-list" class="flex flex-wrap items-center gap-1.5"></div>
                        <button type="button" onclick="resetTaskDetailFilters()" class="axis-filter-clear">Clear all</button>
                    </div>

                    <!-- 5. FEATURE & TASK LIST WORKSPACE CARD -->
                    <div id="td-results-card" class="ent-card overflow-hidden">
                        <!-- Workspace Header + Quick Inline Task Creator Bar -->
                        <div class="px-5 py-3.5 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3 bg-white/[0.03]">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                    <i class="fa-solid fa-list-check text-xs"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-sm font-bold text-white">Task list</h2>
                                        <span id="td-showing-count-pill" class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-semibold">Showing 4 of 4 tasks</span>
                                    </div>
                                    <p class="text-xs text-slate-400">Click any field to change it. Changes save automatically.</p>
                                </div>
                            </div>

                        </div>

                        <!-- TABLE VIEW: wide table; task name and actions stay pinned while the middle scrolls -->
                        <div id="td-table-view-wrap" class="overflow-x-auto axis-hscroll">
                            <table class="w-full min-w-[1200px] ent-table axis-wide-table axis-task-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 pl-5 pr-3 axis-sticky-left">Task</th>
                                        <th class="py-3 px-3">Status</th>
                                        <th class="py-3 px-3">Progress</th>
                                        <th class="py-3 px-3">Assignee</th>
                                        <th class="py-3 px-3">Priority</th>
                                        <th class="py-3 px-3">Time</th>
                                        <th class="py-3 px-3" aria-sort="none" data-task-sort="deadline">
                                            <button type="button" onclick="sortTasksByDeadline()" class="axis-sort-btn">
                                                <span>Deadline</span>
                                                <i class="fa-solid fa-sort" aria-hidden="true"></i>
                                            </button>
                                        </th>
                                        <th class="py-3 px-3">Notes</th>
                                        <th class="py-3 pl-3 pr-5 axis-sticky-right text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tasks-tbody"></tbody>
                            </table>
                        </div>

                        <!-- BENTO CARD GRID VIEW CONTAINER (Hidden by default) -->
                        <div id="td-cards-view-wrap" class="hidden p-5 grid grid-cols-1 md:grid-cols-2 gap-4"></div>
                    </div>
                </div>
