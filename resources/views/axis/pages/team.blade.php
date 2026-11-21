                <!-- ===================================================================== -->
                <!-- PAGE 6: TEAM & LOGIN ACCESS (`team`)                                  -->
                <!-- ===================================================================== -->
                <div id="page-team" @class(['app-page', 'active-page' => $initialPage === 'team', 'space-y-5'])>
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
                                        <th class="py-3 px-3">Email</th>
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
