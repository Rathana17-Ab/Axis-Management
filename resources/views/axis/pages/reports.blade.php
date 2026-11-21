                <!-- ===================================================================== -->
                <!-- PAGE 5: DAILY & WEEKLY CTO REPORTS (`reports`)                        -->
                <!-- ===================================================================== -->
                <div id="page-reports" @class(['app-page', 'active-page' => $initialPage === 'reports', 'space-y-5'])>
                    <div class="ent-card p-5 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-sky-400/15">
                            <div>
                                <h1 class="text-base font-bold text-white">Daily &amp; weekly reports</h1>
                                <p class="text-xs text-slate-400">Structured progress tracking, role verification, and one-click Telegram export.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="relative">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                                    <input type="text" id="rep-search-filter" oninput="renderReportsPage()" placeholder="Search reports..." class="ent-input pl-8 pr-3 py-1.5 text-xs w-40">
                                </div>
                                <select id="rep-type-filter" onchange="renderReportsPage()" class="ent-input px-3 py-1.5 text-xs">
                                    <option value="all">Daily &amp; weekly</option>
                                    <option value="Weekly">Weekly reports</option>
                                    <option value="Daily">Daily reports</option>
                                </select>
                                <select id="rep-handler-filter" onchange="renderReportsPage()" class="ent-input px-3 py-1.5 text-xs">
                                    <option value="all">Everyone</option>
                                    @foreach ($handlers as $handler)
                                    <option value="{{ $handler }}">{{ $handler }}</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="openCreateReportDrawer()" class="btn-primary-glass px-4 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>New report</span>
                                </button>
                            </div>
                        </div>

                        <div id="reports-kpi-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3"></div>

                        <div class="overflow-x-auto">
                            <table class="w-full ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-3 w-36">Type / Period</th>
                                        <th class="py-3 px-3 w-40">Person</th>
                                        <th class="py-3 px-3 w-40">Project</th>
                                        <th class="py-3 px-3">What got done &amp; Scope</th>
                                        <th class="py-3 px-3 w-36">Blockers</th>
                                        <th class="py-3 px-3 text-center w-20">Hours</th>
                                        <th class="py-3 px-3 text-right w-36">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="reports-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ent-card p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-white">Latest issues &amp; fixes</h2>
                                <p class="text-xs text-slate-400">Pulled from the issue notes on every task.</p>
                            </div>
                        </div>
                        <div id="reports-dev-issues-feed" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                    </div>
                </div>
