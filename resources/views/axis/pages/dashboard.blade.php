                <!-- ===================================================================== -->
                <!-- PAGE 0: DASHBOARD (`dashboard`) — the whole system at a glance, filled by renderDashboard() -->
                <!-- ===================================================================== -->
                <div id="page-dashboard" @class(['app-page', 'active-page' => $initialPage === 'dashboard', 'space-y-5'])>

                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pt-1">
                        <div>
                            <h1 class="text-xl font-bold text-white tracking-tight">Dashboard</h1>
                            <p class="text-xs text-slate-300/90 mt-1 max-w-3xl">Where every project, module and task stands right now, and who is on what.</p>
                            <p id="dash-scope" class="text-xs text-slate-400 mt-1.5">—</p>
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

                    <!-- 1. KPI strip (x-axis.kpi-card) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" id="dash-kpi-strip">
                        <x-axis.kpi-card key="dash-tasks" accent="emerald" icon="fa-circle-check" status="Done" title="Tasks finished" />
                        <x-axis.kpi-card key="dash-progress" accent="cyan" icon="fa-chart-line" status="Avg" title="Average progress" />
                        <x-axis.kpi-card key="dash-overdue" accent="amber" icon="fa-calendar-xmark" status="On track" title="Overdue tasks" />
                        <x-axis.kpi-card key="dash-issues" accent="violet" icon="fa-bug" status="All fixed" title="Open issues" />
                    </div>

                    <!-- 2. What needs attention: the most actionable list first -->
                    <section class="ent-card overflow-hidden" aria-labelledby="dash-attention-title">
                        <div class="px-5 py-3.5 border-b border-sky-400/15 flex items-center justify-between gap-3">
                            <div>
                                <h2 id="dash-attention-title" class="text-sm font-bold text-white">Needs attention</h2>
                                <p class="text-xs text-slate-400">Late work, deadlines this week, open issues and unassigned tasks.</p>
                            </div>
                            <span id="dash-attention-count" class="hidden px-2 py-0.5 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-200 text-xs font-semibold shrink-0"></span>
                        </div>
                        <ul id="dash-attention" class="divide-y divide-white/[0.06]"></ul>
                    </section>

                    <!-- 3. Modules, soonest deadline first -->
                    <section class="ent-card overflow-hidden" aria-labelledby="dash-modules-title">
                        <div class="px-5 py-3.5 border-b border-sky-400/15 flex items-center justify-between gap-3">
                            <div>
                                <h2 id="dash-modules-title" class="text-sm font-bold text-white">Modules</h2>
                                <p class="text-xs text-slate-400">Soonest deadline first. Click a module to open its tasks.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto axis-hscroll">
                            <table class="w-full min-w-[720px] ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-2.5 whitespace-nowrap pl-5 pr-3">Module</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Lead</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Tasks done</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Progress</th>
                                        <th class="py-2.5 whitespace-nowrap px-3">Deadline</th>
                                        <th class="py-2.5 whitespace-nowrap pl-3 pr-5 text-right">Open issues</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-modules-body"></tbody>
                            </table>
                        </div>
                    </section>

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
