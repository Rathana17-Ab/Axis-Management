                <!-- ===================================================================== -->
                <!-- PAGE 0: DASHBOARD (`dashboard`) — module, project, task and team lists, filled by renderDashboard() -->
                <!-- ===================================================================== -->
                <div id="page-dashboard" @class(['app-page', 'active-page' => $initialPage === 'dashboard', 'space-y-5'])>

                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pt-1">
                        <div>
                            <h1 class="text-xl font-bold text-white tracking-tight">Dashboard</h1>
                            <p class="text-xs text-slate-300/90 mt-1 max-w-3xl">Module, project, task and team lists.</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 self-start">
                            <button type="button" onclick="switchPage('modules')" class="btn-glass px-3.5 py-2 text-xs font-medium flex items-center gap-2">
                                <i class="fa-solid fa-cubes text-[11px]" aria-hidden="true"></i>
                                <span>All modules</span>
                            </button>
                            <button type="button" onclick="switchPage('reports')" class="btn-glass px-3.5 py-2 text-xs font-medium flex items-center gap-2">
                                <i class="fa-solid fa-file-lines text-[11px]" aria-hidden="true"></i>
                                <span>Reports</span>
                            </button>
                        </div>
                    </div>

                    <!-- 1. Modules, with every card visible and wrapped -->
                    <div class="space-y-2" data-dashboard-row="modules">
                        <div id="dash-modules-toolbar" class="ent-card flex w-full flex-wrap items-center gap-2 px-3 py-2.5" aria-labelledby="dash-modules-title">
                            <input type="search" id="dash-module-search" oninput="setDashboardModuleSearch(this.value)"
                                   placeholder="Search modules…" aria-label="Search modules"
                                   class="ent-input h-8 w-36 sm:w-52 grow px-2 text-[11px] rounded-lg">
                            <select id="dash-module-project-filter" onchange="setDashboardModuleProjectFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[140px]" aria-label="Filter modules by project">
                                <option value="all">All projects</option>
                                @foreach ($axisProjects as $axisProject)
                                    <option value="{{ $axisProject->id }}">{{ $axisProject->name }}</option>
                                @endforeach
                            </select>
                            <select id="dash-module-status-filter" onchange="setDashboardModuleStatusFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[130px]" aria-label="Filter modules by status">
                                <option value="all">All statuses</option>
                                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-[11px] text-slate-400 shrink-0">
                                Order by
                                <select id="dash-modules-order" onchange="setDashboardModuleOrder(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg" aria-label="Order modules by">
                                    <option value="order:asc" selected>Order number</option>
                                    <option value="deadline:asc">Deadline</option>
                                    <option value="name:asc">Name</option>
                                    <option value="progress:desc">Progress</option>
                                    <option value="issues:desc">Open issues</option>
                                </select>
                            </label>
                        </div>
                        <section class="ent-card overflow-hidden" aria-labelledby="dash-modules-title">
                            <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                        <i class="fa-solid fa-cubes text-xs" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 id="dash-modules-title" class="text-sm font-bold text-white">Modules</h2>
                                            <span id="dash-modules-count" class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-semibold">0 modules</span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            Click a card to open the Modules page.
                                            <span id="dash-modules-sort-label" class="text-slate-500">· Sorted by: Order number</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div id="dash-modules-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5"></div>
                        </section>
                    </div>

                    <!-- 2. Project list, horizontally scrollable cards -->
                    <div class="space-y-2" data-dashboard-row="project">
                        <div id="dash-project-toolbar" class="ent-card flex w-full flex-wrap items-center gap-2 px-3 py-2.5" aria-labelledby="dash-project-title">
                            <input type="search" id="dash-project-search" oninput="setDashboardProjectSearch(this.value)"
                                   placeholder="Search projects…" aria-label="Search projects"
                                   class="ent-input h-8 w-36 sm:w-52 grow px-2 text-[11px] rounded-lg">
                            <select id="dash-project-status-filter" onchange="setDashboardProjectStatusFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[130px]" aria-label="Filter projects by status">
                                <option value="all">All statuses</option>
                                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-[11px] text-slate-400 shrink-0">
                                Order by
                                <select id="dash-project-order" onchange="setDashboardProjectOrder(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg" aria-label="Order projects by">
                                    <option value="name:asc" selected>Name (A–Z)</option>
                                    <option value="status:asc">Status</option>
                                    <option value="progress:desc">Progress (highest)</option>
                                    <option value="progress:asc">Progress (lowest)</option>
                                    <option value="deadline:asc">Deadline (soonest)</option>
                                    <option value="issues:desc">Open issues (most)</option>
                                    <option value="modules:desc">Modules (most)</option>
                                </select>
                            </label>
                        </div>
                        <section class="ent-card overflow-hidden" aria-labelledby="dash-project-title">
                            <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                        <i class="fa-solid fa-folder-tree text-xs" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 id="dash-project-title" class="text-sm font-bold text-white">Project list</h2>
                                            <span id="dash-project-count" class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-semibold">0 projects</span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            Click a card to open the Projects page.
                                            <span id="dash-project-sort-label" class="text-slate-500">· Sorted by: Name (A–Z)</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1" data-dashboard-scroll>
                                    <button type="button" data-scroll-dir="-1" onclick="scrollDashboardCards('dash-project-cards', -1)" class="axis-row-btn disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Scroll projects left">
                                        <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" data-scroll-dir="1" onclick="scrollDashboardCards('dash-project-cards', 1)" class="axis-row-btn disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Scroll projects right">
                                        <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="dash-project-cards" class="flex gap-3 p-4 overflow-x-auto axis-hscroll"></div>
                        </section>
                    </div>

                    <!-- 3. Task list, horizontally scrollable cards -->
                    <div class="space-y-2" data-dashboard-row="task">
                        <div id="dash-task-toolbar" class="ent-card flex w-full flex-wrap items-center gap-2 px-3 py-2.5" aria-labelledby="dash-task-title">
                            <input type="search" id="dash-task-search" oninput="setDashboardTaskSearch(this.value)"
                                   placeholder="Search tasks…" aria-label="Search tasks"
                                   class="ent-input h-8 w-36 sm:w-52 grow px-2 text-[11px] rounded-lg">
                            <select id="dash-task-module-filter" onchange="setDashboardTaskModuleFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[150px]" aria-label="Filter tasks by module">
                                <option value="all">All modules</option>
                                @foreach ($projects as $module)
                                    <option value="{{ $module->id }}">{{ $module->code }} — {{ $module->name }}</option>
                                @endforeach
                            </select>
                            <select id="dash-task-status-filter" onchange="setDashboardTaskStatusFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[130px]" aria-label="Filter tasks by status">
                                <option value="all">All statuses</option>
                                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                                @endforeach
                            </select>
                            <select id="dash-task-priority-filter" onchange="setDashboardTaskPriorityFilter(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg max-w-[110px]" aria-label="Filter tasks by priority">
                                <option value="all">All priorities</option>
                                @foreach (\App\Enums\TaskPriority::cases() as $taskPriority)
                                    <option value="{{ $taskPriority->value }}">{{ $taskPriority->value }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-[11px] text-slate-400 shrink-0">
                                Order by
                                <select id="dash-task-order" onchange="setDashboardTaskOrder(this.value)" class="ent-input h-8 px-2 text-[11px] rounded-lg" aria-label="Order tasks by">
                                    <option value="deadline:asc" selected>Deadline</option>
                                    <option value="priority:asc">Priority</option>
                                    <option value="status:asc">Status</option>
                                    <option value="name:asc">Name</option>
                                </select>
                            </label>
                        </div>
                        <section class="ent-card overflow-hidden" aria-labelledby="dash-task-title">
                            <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center text-sky-300">
                                        <i class="fa-solid fa-list-check text-xs" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h2 id="dash-task-title" class="text-sm font-bold text-white">Task list</h2>
                                            <span id="dash-task-count" class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-xs font-semibold">0 tasks</span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            Click a card to open the Tasks page.
                                            <span id="dash-task-sort-label" class="text-slate-500">· Sorted by: Deadline</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1" data-dashboard-scroll>
                                    <button type="button" data-scroll-dir="-1" onclick="scrollDashboardCards('dash-task-cards', -1)" class="axis-row-btn disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Scroll tasks left">
                                        <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" data-scroll-dir="1" onclick="scrollDashboardCards('dash-task-cards', 1)" class="axis-row-btn disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Scroll tasks right">
                                        <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="dash-task-cards" class="flex gap-3 p-4 overflow-x-auto axis-hscroll"></div>
                        </section>
                    </div>

                    <!-- 4. Team workload: accountability per person -->
                    <section class="ent-card overflow-hidden" aria-labelledby="dash-team-title">
                        <div class="px-5 py-3.5 border-b border-sky-400/15">
                            <h2 id="dash-team-title" class="text-sm font-bold text-white">Team workload</h2>
                            <p class="text-xs text-slate-400">Every active employee and the tasks assigned to them, busiest first.</p>
                        </div>
                        <div class="overflow-x-auto axis-hscroll">
                            <table class="w-full min-w-[720px] ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-2.5 whitespace-nowrap pl-5 pr-3">Person</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Open tasks</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Overdue</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Finished</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Time logged</th>
                                        <th class="py-2.5 whitespace-nowrap pl-3 pr-5">Right now</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-team-body"></tbody>
                            </table>
                        </div>
                    </section>

                </div>
