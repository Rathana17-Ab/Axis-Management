                <!-- ===================================================================== -->
                <!-- PAGE 10: P18 ACCESS & UPGRADE STATES (`p18`)                           -->
                <!-- ===================================================================== -->
                <div id="page-p18" @class(['app-page', 'active-page' => $initialPage === 'p18', 'space-y-5'])>
                    <div class="ent-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-sky-400/15 flex items-center justify-between gap-3">
                            <div>
                                <h1 class="text-base font-bold text-white">Access &amp; upgrade states</h1>
                                <p class="text-xs text-slate-400">Current P18 feature access and upgrade status.</p>
                            </div>
                            <button type="button" onclick="reloadWorkspaceSection('p18')" class="btn-glass px-3 py-1.5 text-xs font-semibold">
                                <i class="fa-solid fa-rotate-right text-[10px]"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full ent-table text-left border-collapse">
                                <thead>
                                    <tr>
                                        <th class="py-3 px-3">Feature</th>
                                        <th class="py-3 px-3">Description</th>
                                        <th class="py-3 px-3 text-center">State</th>
                                    </tr>
                                </thead>
                                <tbody id="feature-upgrades-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
