<x-axis.drawer id="drawer-position-edit" mode="edit" icon="fa-id-badge" size="sm"
               breadcrumb="Team" title="Edit position"
               description="Update the position label and who may assign tasks."
               submit="handleUpdatePosition(event)">
    <x-axis.drawer.section>
        <x-axis.drawer.field label="Name" for="position-edit-name" name="name" required span="full">
            <input type="text" id="position-edit-name" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="position-edit-description" name="description" span="full">
            <textarea id="position-edit-description" rows="3" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Can assign tasks" for="position-edit-leader" name="is_leader" span="full">
            <label class="flex items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" id="position-edit-leader" value="1" class="h-4 w-4 accent-sky-400">
                <span>Members holding this position can assign tasks to others</span>
            </label>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Availability" for="position-edit-active" name="is_active" span="full">
            <label class="flex items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" id="position-edit-active" value="1" class="h-4 w-4 accent-sky-400">
                <span>Available for team members</span>
            </label>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
