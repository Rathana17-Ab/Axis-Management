<x-axis.drawer id="modal-team-member" mode="create" icon="fa-user-plus"
               breadcrumb="Team" title="Add a person"
               description="They'll be able to sign in and be assigned to projects and tasks."
               submit="handleSaveTeamMember(event)" submit-label="Add person" busy-label="Adding…">
    <x-axis.drawer.section title="Profile">
        <x-axis.drawer.field label="Name" for="tm-name-input" name="name" required>
            <input type="text" id="tm-name-input" placeholder="e.g. Sokha Vann" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Job title" for="tm-pos-input" name="job_title" required>
            <input type="text" id="tm-pos-input" placeholder="e.g. Laravel developer" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Department" for="tm-dept-input" name="department_id">
            <select id="tm-dept-input" class="axis-input" required></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Position" for="tm-position-input" name="position_id" required hint="Leaders can assign tasks to others.">
            <select id="tm-position-input" class="axis-input" required></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="tm-status-input" name="status" required hint="Only active employees can be given tasks.">
            <select id="tm-status-input" class="axis-input" required>
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="tm-desc-input" name="description" span="full">
            <textarea id="tm-desc-input" rows="2" placeholder="e.g. Owns the payroll module" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Login username (email)" for="tm-email-input" name="email" required span="full">
            <input type="email" id="tm-email-input" placeholder="sokha@eocambo.dev" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
