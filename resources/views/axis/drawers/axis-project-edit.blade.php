<x-axis.drawer id="drawer-axis-project-edit" mode="edit" icon="fa-folder-tree" size="sm"
               breadcrumb="Projects" title="Edit project"
               description="Change the name, dates, status or description."
               submit="handleUpdateAxisProject(event)">
    <x-axis.drawer.section>
        <x-axis.drawer.field label="Project name" for="edit-axis-project-name" name="name" required span="full">
            <input type="text" id="edit-axis-project-name" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Start date" for="edit-axis-project-start" name="start_date">
            <input type="date" id="edit-axis-project-start" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Deadline" for="edit-axis-project-deadline" name="deadline">
            <input type="date" id="edit-axis-project-deadline" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="edit-axis-project-status" name="status" span="full">
            <select id="edit-axis-project-status" class="axis-input">
                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="edit-axis-project-desc" name="description" span="full">
            <textarea id="edit-axis-project-desc" rows="3" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
