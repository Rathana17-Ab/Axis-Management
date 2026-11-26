<x-axis.drawer id="drawer-team-member-edit" mode="edit" icon="fa-user-pen" size="sm"
               breadcrumb="Team" title="Edit person"
               description="Change their department, position, description or status."
               submit="handleUpdateTeamMember(event)">
    <x-axis.drawer.section>
        <x-axis.drawer.field label="Department" for="tm-edit-dept" name="department_id" span="full" required>
            <select id="tm-edit-dept" class="axis-input" required></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Position" for="tm-edit-position" name="position_id" required hint="Leaders can assign tasks to others.">
            <select id="tm-edit-position" class="axis-input" required></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="tm-edit-status" name="status" required hint="Only active employees can be given tasks.">
            <select id="tm-edit-status" class="axis-input" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="tm-edit-desc" name="description" span="full">
            <textarea id="tm-edit-desc" rows="3" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
