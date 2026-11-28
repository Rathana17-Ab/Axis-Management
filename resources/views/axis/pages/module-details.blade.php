                <!-- ===================================================================== -->
                <!-- PAGE: MODULE DETAILS (`module-details` - `admin/module-details/{module}`) -->
                <!-- ===================================================================== -->
                <div id="page-module-details" @class(['app-page', 'active-page' => $initialPage === 'module-details', 'space-y-5'])>

                    <!-- Module identity and actions -->
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pt-1">
                        <div class="min-w-0 space-y-3">
                            <button type="button" onclick="switchPage('dashboard')" class="axis-hero-back">
                                <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                <span>Back to dashboard</span>
                            </button>

                            <div class="flex items-start gap-3">
                                <span id="md-identity-badge" class="w-10 h-10 rounded-xl bg-sky-500/15 text-sky-200 border border-sky-400/30 font-bold text-sm flex items-center justify-center shrink-0">—</span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <h1 id="md-title" class="text-xl font-bold text-white tracking-tight">—</h1>
                                        <span id="md-code-badge" class="axis-hero-code">—</span>
                                        <span id="md-sort-order" class="text-xs text-slate-400">Order —</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-400">
                                        <span id="md-project-name">—</span>
                                        <span class="text-slate-600" aria-hidden="true">·</span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <span id="md-product-dot" class="w-1.5 h-1.5 rounded-full bg-slate-400 shrink-0" aria-hidden="true"></span>
                                            <span id="md-product">—</span>
                                        </span>
                                    </div>
                                    <p id="md-description" class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-400 whitespace-pre-line">—</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0 self-start">
                            <button type="button" onclick="openProjectEdit(activeProjectId)" class="btn-glass axis-hero-action" title="Edit this module">
                                <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                                <span>Edit</span>
                            </button>
                            <button type="button" onclick="openAddTaskFor(activeProjectId)" class="btn-glass axis-hero-action" title="Add a task to this module">
                                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                                <span>Add task</span>
                            </button>
                            <button type="button" onclick="openProjectTaskList(activeProjectId)" class="btn-primary-glass axis-hero-action">
                                <i class="fa-solid fa-list-check text-xs" aria-hidden="true"></i>
                                <span>Open task list</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                        <!-- Delivery: the live status/progress/workload shown on every module row -->
                        <section class="ent-card overflow-hidden lg:col-span-2" aria-labelledby="md-delivery-title">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
                                <div>
                                    <h2 id="md-delivery-title" class="text-sm font-bold text-white">Delivery</h2>
                                    <p class="mt-0.5 text-xs text-slate-400">Status, progress and timeline from the module record.</p>
                                </div>
                                <div id="md-status-field" class="flex items-center gap-1.5"></div>
                            </div>
                            <div class="px-5 py-5 space-y-5">
                                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div class="min-w-0">
                                        <h3 class="text-xs font-semibold text-slate-300">Progress</h3>
                                        <div id="md-progress" class="mt-2"></div>
                                        <p class="mt-2 text-xs text-slate-500">Average of task progress in this module.</p>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-xs font-semibold text-slate-300">Timeline</h3>
                                        <div id="md-timeline" class="mt-2 text-sm text-slate-100">—</div>
                                        <div id="md-time-used" class="mt-2"></div>
                                        <div id="md-due" class="text-xs mt-1">—</div>
                                    </div>
                                </div>

                                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 border-t border-white/[0.06] pt-4 text-xs sm:grid-cols-3">
                                    <div>
                                        <dt class="text-slate-500">Tasks done</dt>
                                        <dd id="md-tasks" class="mt-0.5 font-mono font-semibold text-slate-100">0 / 0</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Open issues</dt>
                                        <dd id="md-issues" class="mt-0.5">None</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Support ends</dt>
                                        <dd id="md-support-ends" class="mt-0.5 font-medium text-slate-100">—</dd>
                                    </div>
                                </dl>
                            </div>
                        </section>

                        <!-- People: lead, team and customer for this module -->
                        <section class="ent-card overflow-hidden" aria-labelledby="md-people-title">
                            <div class="border-b border-white/[0.06] px-5 py-4">
                                <h2 id="md-people-title" class="text-sm font-bold text-white">People</h2>
                                <p class="mt-0.5 text-xs text-slate-400">Lead and team assigned to this module.</p>
                            </div>
                            <div class="px-5 py-5 space-y-5 text-sm">
                                <div>
                                    <h3 class="text-xs font-semibold text-slate-300">Lead</h3>
                                    <div id="md-lead" class="mt-2 flex items-center gap-2 text-slate-100">—</div>
                                </div>
                                <div class="border-t border-white/[0.06] pt-4">
                                    <h3 id="md-team-title" class="text-xs font-semibold text-slate-300">Team</h3>
                                    <div id="md-team" class="mt-2"></div>
                                    <p id="md-team-names" class="mt-2 text-xs text-slate-400">—</p>
                                </div>
                                <div class="border-t border-white/[0.06] pt-4">
                                    <h3 class="text-xs font-semibold text-slate-300">Customer</h3>
                                    <p id="md-customer" class="mt-1 text-slate-100">—</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Reports filed for this module -->
                    <section class="ent-card overflow-hidden" aria-labelledby="md-reports-title">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.06] px-5 py-4">
                            <div>
                                <h2 id="md-reports-title" class="text-sm font-bold text-white">Reports</h2>
                                <p class="mt-0.5 text-xs text-slate-400">Daily and weekly reports filed for this module.</p>
                            </div>
                            <span id="md-reports-count" class="text-xs text-slate-400">0 reports</span>
                        </div>
                        <div id="md-reports" class="p-5"></div>
                    </section>
                </div>
