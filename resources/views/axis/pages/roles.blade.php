                <!-- ===================================================================== -->
                <!-- PAGE 7: ADMINISTRATION — USERS, ROLES & TEAMS (`roles`)               -->
                <!-- ===================================================================== -->
                <div id="page-roles" @class(['app-page', 'active-page' => $initialPage === 'roles', 'space-y-5'])>
                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h1 class="text-base font-bold text-white">Administration</h1>
                                <p class="text-xs text-slate-400">Roles, permissions, teams and each person's workspace access.</p>
                            </div>
                            <button type="button" data-admin-only onclick="reloadWorkspaceSection('roles')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                <i class="fa-solid fa-rotate-right text-[10px]"></i>
                                <span>Refresh</span>
                            </button>
                        </div>

                        <div class="grid gap-5 p-5 xl:grid-cols-2">
                            <section class="min-w-0">
                                <h2 class="text-sm font-bold text-white mb-3">Roles &amp; permissions</h2>
                                <div class="overflow-x-auto border border-white/[0.06] rounded-xl">
                                    <table class="w-full ent-table text-left border-collapse">
                                        <thead>
                                            <tr>
                                                <th class="py-3 px-3">Role</th>
                                                <th class="py-3 px-3">Permissions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="roles-tbody"></tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="min-w-0">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <h2 class="text-sm font-bold text-white">Teams</h2>
                                    <div class="flex items-center gap-2" data-admin-only>
                                        <button type="button" onclick="toggleWorkspaceForm('form-team-members')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                            Members
                                        </button>
                                        <button type="button" onclick="toggleWorkspaceForm('form-team-create')" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">
                                            New team
                                        </button>
                                    </div>
                                </div>
                                <form id="form-team-create" data-admin-only onsubmit="createWorkspaceTeam(event)" class="hidden mb-3 grid gap-2 rounded-xl border border-sky-400/20 bg-white/[0.03] p-3">
                                    <label class="block">
                                        <span class="block text-xs font-semibold text-slate-400 mb-1">Name</span>
                                        <input name="name" type="text" required maxlength="255" class="axis-input w-full h-9 px-3 text-sm" placeholder="e.g. Sales Support">
                                    </label>
                                    <label class="block">
                                        <span class="block text-xs font-semibold text-slate-400 mb-1">Description</span>
                                        <input name="description" type="text" maxlength="5000" class="axis-input w-full h-9 px-3 text-sm" placeholder="What this team handles">
                                    </label>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" onclick="toggleWorkspaceForm('form-team-create')" class="btn-glass px-3 py-1.5 text-xs">Cancel</button>
                                        <button type="submit" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">Save team</button>
                                    </div>
                                </form>
                                <div class="overflow-x-auto border border-white/[0.06] rounded-xl">
                                    <table class="w-full ent-table text-left border-collapse">
                                        <thead>
                                            <tr>
                                                <th class="py-3 px-3">Team</th>
                                                <th class="py-3 px-3">Members</th>
                                                <th class="py-3 px-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="teams-tbody"></tbody>
                                    </table>
                                </div>
                                <form id="form-team-members" data-admin-only onsubmit="saveWorkspaceTeamMembers(event)" class="hidden mt-3 grid gap-2 rounded-xl border border-white/[0.06] bg-white/[0.03] p-3">
                                    <label class="block">
                                        <span class="block text-xs font-semibold text-slate-400 mb-1">Team</span>
                                        <select id="team-members-team" class="axis-input w-full h-9 px-3 text-sm"></select>
                                    </label>
                                    <label class="block">
                                        <span class="block text-xs font-semibold text-slate-400 mb-1">Members</span>
                                        <select id="team-members-list" multiple size="4" class="axis-input w-full px-3 py-2 text-sm"></select>
                                    </label>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" onclick="toggleWorkspaceForm('form-team-members')" class="btn-glass px-3 py-1.5 text-xs">Cancel</button>
                                        <button type="submit" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">Save members</button>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <section class="border-t border-white/[0.06] p-5">
                            <h2 class="text-sm font-bold text-white mb-3">User roles</h2>
                            <div class="overflow-x-auto border border-white/[0.06] rounded-xl">
                                <table class="w-full ent-table text-left border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-3">Person</th>
                                            <th class="py-3 px-3">Teams</th>
                                            <th class="py-3 px-3">Workspace roles</th>
                                        </tr>
                                    </thead>
                                    <tbody id="user-roles-tbody"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
