<x-axis.drawer id="drawer-project-edit" mode="edit" icon="fa-pen-to-square"
               breadcrumb="PROJ-0000" title="Edit module"
               description="Change any detail of this module, including who is on the team."
               submit="handleUpdateProject(event)">
    <x-axis.drawer.section title="Module" description="What you're building and who it's for.">
        <x-axis.drawer.field label="Project" for="edit-proj-project" name="project_id" required span="full">
            <select id="edit-proj-project" class="axis-input" required>
                @foreach ($axisProjects as $axisProject)
                    <option value="{{ $axisProject->id }}">{{ $axisProject->name }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Module name" for="edit-proj-name" name="name" required span="full">
            <input type="text" id="edit-proj-name" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Customer" for="edit-proj-customer" name="customer_name" required span="full">
            <input type="text" id="edit-proj-customer" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="edit-proj-desc" name="description" span="full">
            <textarea id="edit-proj-desc" rows="3" placeholder="What this module does" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <x-axis.drawer.section title="Planning">
        <x-axis.drawer.field label="Lead" for="edit-proj-manager" name="manager_name" required>
            <select id="edit-proj-manager" class="axis-input" required>
                @foreach ($handlers as $handler)
                    <option value="{{ $handler }}">{{ $handler }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="edit-proj-status" name="status" required>
            <select id="edit-proj-status" class="axis-input" required>
                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Start date → deadline" for="edit-proj-schedule" name="deadline" required span="full">
            <x-axis.date-range-field id="edit-proj-schedule" start-id="edit-proj-start" end-id="edit-proj-deadline" />
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Team" for="edit-proj-team" name="team_members" span="full" hint="Who works on this module. Shown in the module list and used by search.">
            {{-- Filled by openProjectEdit() --}}
            <div id="edit-proj-team" class="axis-team-picker" role="group" aria-label="Team"></div>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
