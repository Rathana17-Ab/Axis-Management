<x-axis.drawer id="drawer-project-create" mode="create" icon="fa-folder-tree" size="sm"
               breadcrumb="Projects" title="New project"
               description="A project groups modules, such as EOT Axis with Axis Sales and Axis Commerce."
               submit="handleCreateAxisProject(event)" submit-label="Create project">
    <x-axis.drawer.section>
        <x-axis.drawer.field label="Project name" for="new-axis-project-name" name="name" required span="full">
            <input type="text" id="new-axis-project-name" placeholder="e.g. EOT Axis" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Start date" for="new-axis-project-start" name="start_date">
            <input type="date" id="new-axis-project-start" value="{{ now()->toDateString() }}" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Deadline" for="new-axis-project-deadline" name="deadline">
            <input type="date" id="new-axis-project-deadline" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="new-axis-project-status" name="status" span="full">
            <select id="new-axis-project-status" class="axis-input">
                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="new-axis-project-desc" name="description" span="full">
            <textarea id="new-axis-project-desc" rows="3" placeholder="What this project covers" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
