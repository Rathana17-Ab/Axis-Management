                <!-- ===================================================================== -->
                <!-- PAGE 6: TEAM & LOGIN ACCESS (`team`)                                  -->
                <!-- ===================================================================== -->
                <div id="page-team" @class(['app-page', 'active-page' => $initialPage === 'team', 'space-y-5'])>
                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="ent-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-sky-400/15 flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-sm font-bold text-white">Departments</h2>
                                    <p class="text-xs text-slate-400">The directory used on each person's profile.</p>
                                </div>
                                <button type="button" onclick="toggleWorkspaceForm('form-department-create')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                    New department
                                </button>
                            </div>
                            <form id="form-department-create" onsubmit="createDepartment(event)" class="hidden m-5 grid gap-2 rounded-xl border border-sky-400/20 bg-white/[0.03] p-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="block text-xs font-semibold text-slate-400 mb-1">Name</span>
                                    <input name="name" type="text" required maxlength="255" class="axis-input w-full h-9 px-3 text-sm" placeholder="e.g. Engineering">
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="block text-xs font-semibold text-slate-400 mb-1">Description</span>
                                    <input name="description" type="text" maxlength="5000" class="axis-input w-full h-9 px-3 text-sm" placeholder="What this department covers">
                                </label>
                                <div class="flex justify-end gap-2 sm:col-span-2">
                                    <button type="button" onclick="toggleWorkspaceForm('form-department-create')" class="btn-glass px-3 py-1.5 text-xs">Cancel</button>
                                    <button type="submit" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">Save department</button>
                                </div>
                            </form>
                            <div class="overflow-x-auto">
                                <table class="w-full ent-table text-left border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-3">Department</th>
                                            <th class="py-3 px-3 text-center">Members</th>
                                            <th class="py-3 px-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="departments-tbody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ent-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-sky-400/15 flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-sm font-bold text-white">Positions</h2>
                                    <p class="text-xs text-slate-400">Leaders can assign tasks to other members.</p>
                                </div>
                                <button type="button" onclick="toggleWorkspaceForm('form-position-create')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                    New position
                                </button>
                            </div>
                            <form id="form-position-create" onsubmit="createPosition(event)" class="hidden m-5 grid gap-2 rounded-xl border border-sky-400/20 bg-white/[0.03] p-3 sm:grid-cols-2">
                                <label class="block">
                                    <span class="block text-xs font-semibold text-slate-400 mb-1">Name</span>
                                    <input name="name" type="text" required maxlength="255" class="axis-input w-full h-9 px-3 text-sm" placeholder="e.g. Team Leader">
                                </label>
                                <label class="block">
                                    <span class="block text-xs font-semibold text-slate-400 mb-1">Description</span>
                                    <input name="description" type="text" maxlength="5000" class="axis-input w-full h-9 px-3 text-sm" placeholder="What this position does">
                                </label>
                                <label class="flex items-center gap-2 sm:col-span-2 text-xs text-slate-300">
                                    <input name="is_leader" type="checkbox" value="1" class="h-4 w-4 accent-sky-400">
                                    <span>Can assign tasks to others</span>
                                </label>
                                <div class="flex justify-end gap-2 sm:col-span-2">
                                    <button type="button" onclick="toggleWorkspaceForm('form-position-create')" class="btn-glass px-3 py-1.5 text-xs">Cancel</button>
                                    <button type="submit" class="btn-primary-glass px-3 py-1.5 text-xs font-semibold">Save position</button>
                                </div>
                            </form>
                            <div class="overflow-x-auto">
                                <table class="w-full ent-table text-left border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="py-3 px-3">Position</th>
                                            <th class="py-3 px-3 text-center">Members</th>
                                            <th class="py-3 px-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="positions-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex items-center justify-between">
                            <div>
                                <h1 class="text-base font-bold text-white">Team</h1>
                                <p class="text-xs text-slate-400">Everyone who can be given tasks: their department, position, status and sign-in access.</p>
                            </div>
                            <button type="button" onclick="openModal('modal-team-member')" class="btn-primary-glass px-4 py-1.5 text-xs font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-user-plus text-[10px]"></i>
                                <span>Add person</span>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-3 text-center w-12">#</th>
                                        <th class="py-3 px-3">Name</th>
                                        <th class="py-3 px-3">Department</th>
                                        <th class="py-3 px-3">Position</th>
                                        <th class="py-3 px-3">Description</th>
                                        <th class="py-3 px-3">Login username</th>
                                        <th class="py-3 px-3 text-center">Status</th>
                                        <th class="py-3 px-3 text-center">Login</th>
                                        <th class="py-3 px-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="team-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
