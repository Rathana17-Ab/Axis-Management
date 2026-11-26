    {{-- Centered create dialog for the team directory. --}}
    <div id="modal-department-create" class="axis-modal-overlay fixed inset-0 z-[245] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="modal-department-create-title"
         onclick="if (event.target === this) closeModal('modal-department-create')">
        <div class="axis-modal w-full max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 pb-4 border-b border-white/[0.08]">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="axis-modal__icon" aria-hidden="true"><i class="fa-solid fa-building text-sm"></i></span>
                    <div class="min-w-0">
                        <h2 id="modal-department-create-title" class="text-sm font-bold text-white">New department</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Add a department to the directory used on each person's profile.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('modal-department-create')" class="axis-topbar-icon-btn shrink-0" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form onsubmit="handleCreateDepartment(event)" class="pt-4 space-y-3" id="form-department-create">
                <div>
                    <label for="new-department-name" class="block text-xs font-semibold text-slate-400 mb-1.5">Name</label>
                    <input type="text" id="new-department-name" placeholder="e.g. Engineering" class="axis-input w-full h-10 px-3 text-sm" required maxlength="255" data-autofocus>
                </div>
                <div>
                    <label for="new-department-description" class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
                    <textarea id="new-department-description" rows="3" placeholder="What this department covers" class="axis-input w-full px-3 py-2 text-sm" maxlength="5000"></textarea>
                </div>
                <div class="pt-1 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('modal-department-create')" class="btn-glass h-10 px-4 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="btn-primary-glass h-10 px-5 text-xs font-semibold">Create department</button>
                </div>
            </form>
        </div>
    </div>
