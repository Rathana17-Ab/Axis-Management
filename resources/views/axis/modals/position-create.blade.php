    {{-- Centered create dialog for the team directory. --}}
    <div id="modal-position-create" class="axis-modal-overlay fixed inset-0 z-[245] hidden items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="modal-position-create-title"
         onclick="if (event.target === this) closeModal('modal-position-create')">
        <div class="axis-modal w-full max-w-md max-h-[calc(100dvh-2rem)] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 pb-4 border-b border-white/[0.08]">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="axis-modal__icon" aria-hidden="true"><i class="fa-solid fa-id-badge text-sm"></i></span>
                    <div class="min-w-0">
                        <h2 id="modal-position-create-title" class="text-sm font-bold text-white">New position</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Add a position and choose whether its members can assign tasks.</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('modal-position-create')" class="axis-topbar-icon-btn shrink-0" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <form onsubmit="handleCreatePosition(event)" class="pt-4 space-y-3" id="form-position-create">
                <div>
                    <label for="new-position-name" class="block text-xs font-semibold text-slate-400 mb-1.5">Name</label>
                    <input type="text" id="new-position-name" placeholder="e.g. Team Leader" class="axis-input w-full h-10 px-3 text-sm" required maxlength="255" data-autofocus>
                </div>
                <div>
                    <label for="new-position-description" class="block text-xs font-semibold text-slate-400 mb-1.5">Description</label>
                    <textarea id="new-position-description" rows="3" placeholder="What this position does" class="axis-input w-full px-3 py-2 text-sm" maxlength="5000"></textarea>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-300">
                    <input type="checkbox" id="new-position-leader" value="1" class="h-4 w-4 accent-sky-400">
                    <span>Members holding this position can assign tasks to others</span>
                </label>
                <div class="pt-1 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('modal-position-create')" class="btn-glass h-10 px-4 text-xs font-semibold">Cancel</button>
                    <button type="submit" class="btn-primary-glass h-10 px-5 text-xs font-semibold">Create position</button>
                </div>
            </form>
        </div>
    </div>
