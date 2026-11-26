                <!-- ===================================================================== -->
                <!-- PAGE 8: WORK QUEUES & QUEUE SHARING (`work-queues`)                   -->
                <!-- ===================================================================== -->
                <div id="page-work-queues" @class(['app-page', 'active-page' => $initialPage === 'work-queues', 'space-y-5'])>
                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h1 class="text-base font-bold text-white">Work queues</h1>
                                <p class="text-xs text-slate-400">Shared pipelines and the people who can work in them.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="reloadWorkspaceSection('queues')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                    <i class="fa-solid fa-rotate-right text-[10px]"></i>
                                    <span>Refresh</span>
                                </button>
                                <button type="button" data-queue-admin-only onclick="toggleWorkspaceForm('form-work-queue-create')" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">
                                    New queue
                                </button>
                            </div>
                        </div>

                        <form id="form-work-queue-create" data-queue-admin-only onsubmit="createWorkspaceQueue(event)" class="hidden m-5 grid gap-2 rounded-xl border border-sky-400/20 bg-white/[0.03] p-3 lg:grid-cols-2">
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Name</span>
                                <input name="name" type="text" required maxlength="255" class="axis-input w-full h-9 px-3 text-sm" placeholder="e.g. Renewals">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Team</span>
                                <select name="team_id" id="queue-team-select" class="axis-input w-full h-9 px-3 text-sm">
                                    <option value="">No team</option>
                                </select>
                            </label>
                            <label class="block lg:col-span-2">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Assignment rule</span>
                                <input name="assignment_rule" type="text" maxlength="255" class="axis-input w-full h-9 px-3 text-sm" placeholder="How work is assigned">
                            </label>
                            <label class="block lg:col-span-2">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Description</span>
                                <input name="description" type="text" maxlength="5000" class="axis-input w-full h-9 px-3 text-sm" placeholder="What this queue is for">
                            </label>
                            <div class="flex justify-end gap-2 lg:col-span-2">
                                <button type="button" onclick="toggleWorkspaceForm('form-work-queue-create')" class="btn-glass px-3 py-1.5 text-xs">Cancel</button>
                                <button type="submit" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">Save queue</button>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-3">Queue</th>
                                        <th class="py-3 px-3">Team</th>
                                        <th class="py-3 px-3">Assignment rule</th>
                                        <th class="py-3 px-3">Members</th>
                                        <th class="py-3 px-3 text-center">Your access</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="work-queues-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ent-card overflow-hidden" data-queue-admin-only>
                        <div class="px-5 py-4 border-b border-sky-400/15 flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-bold text-white">Queue sharing</h2>
                                <p class="text-xs text-slate-400">Give people viewer or editor access to a queue.</p>
                            </div>
                            <button type="button" onclick="toggleWorkspaceForm('form-queue-share')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                Share queue
                            </button>
                        </div>
                        <form id="form-queue-share" onsubmit="saveWorkspaceQueueMembers(event)" class="hidden m-5 grid gap-2 rounded-xl border border-white/[0.06] bg-white/[0.03] p-3 lg:grid-cols-3">
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Queue</span>
                                <select id="queue-share-queue" class="axis-input w-full h-9 px-3 text-sm"></select>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">People</span>
                                <select id="queue-share-members" multiple size="4" class="axis-input w-full px-3 py-2 text-sm"></select>
                            </label>
                            <div class="block">
                                <span class="block text-xs font-semibold text-slate-400 mb-1">Access</span>
                                <select id="queue-share-access" class="axis-input w-full h-9 px-3 text-sm">
                                    <option value="viewer">Viewer</option>
                                    <option value="editor">Editor</option>
                                </select>
                                <button type="submit" class="btn-primary-glass w-full mt-3 px-3 py-1.5 text-xs font-semibold">Save access</button>
                            </div>
                        </form>
                    </div>
                </div>
