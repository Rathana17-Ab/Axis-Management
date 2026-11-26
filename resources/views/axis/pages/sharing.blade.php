                <!-- ===================================================================== -->
                <!-- PAGE 9: RECORD SHARING (`sharing`)                                     -->
                <!-- ===================================================================== -->
                <div id="page-sharing" @class(['app-page', 'active-page' => $initialPage === 'sharing', 'space-y-5'])>
                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h1 class="text-base font-bold text-white">Record sharing</h1>
                                <p class="text-xs text-slate-400">Projects, modules, tasks and reports shared with people.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="reloadWorkspaceSection('sharing')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                    <i class="fa-solid fa-rotate-right text-[10px]"></i>
                                    <span>Refresh</span>
                                </button>
                                <button type="button" data-record-admin-only onclick="toggleWorkspaceForm('form-record-share')" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">
                                    Share record
                                </button>
                            </div>
                        </div>

                        <form id="form-record-share" data-record-admin-only onsubmit="createWorkspaceRecordShare(event)" class="hidden m-5 grid gap-2 rounded-xl border border-sky-400/20 bg-white/[0.03] p-3 lg:grid-cols-4">
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Record type</span>
                                <select id="share-record-type" onchange="populateWorkspaceRecordOptions()" class="axis-input w-full h-9 px-3 text-sm">
                                    <option value="project">Project</option>
                                    <option value="module">Module</option>
                                    <option value="task">Task</option>
                                    <option value="report">Report</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Record</span>
                                <select id="share-record-id" class="axis-input w-full h-9 px-3 text-sm"></select>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Person</span>
                                <select id="share-user-id" class="axis-input w-full h-9 px-3 text-sm"></select>
                            </label>
                            <div class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Ability</span>
                                <select id="share-ability" class="axis-input w-full h-9 px-3 text-sm">
                                    <option value="view">View</option>
                                    <option value="update">Update</option>
                                </select>
                                <button type="submit" class="btn-primary-glass w-full mt-3 px-3 py-1.5 text-xs font-semibold">Save share</button>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-3">Record</th>
                                        <th class="py-3 px-3">Type</th>
                                        <th class="py-3 px-3">Shared with</th>
                                        <th class="py-3 px-3 text-center">Ability</th>
                                        <th class="py-3 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="record-shares-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
