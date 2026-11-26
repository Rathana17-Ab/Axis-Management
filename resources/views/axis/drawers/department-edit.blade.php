<x-axis.drawer id="drawer-department-edit" mode="edit" icon="fa-building" size="sm"
               breadcrumb="Team" title="Edit department"
               description="Rename the department or update what it covers."
               submit="handleUpdateDepartment(event)">
    <x-axis.drawer.section>
        <x-axis.drawer.field label="Name" for="dept-edit-name" name="name" required span="full">
            <input type="text" id="dept-edit-name" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="dept-edit-description" name="description" span="full">
            <textarea id="dept-edit-description" rows="3" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Availability" for="dept-edit-active" name="is_active" span="full">
            <label class="flex items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" id="dept-edit-active" value="1" class="h-4 w-4 accent-sky-400">
                <span>Available for team members</span>
            </label>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
