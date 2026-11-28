<x-axis.drawer id="drawer-task-edit" mode="edit" icon="fa-pen-to-square" size="lg"
               breadcrumb="Tasks" title="Edit task"
               description="Change any detail of this task. The timer and issue notes stay as they are."
               submit="handleUpdateTask(event)">
    <x-axis.drawer.section title="Task">
        <x-axis.drawer.field label="Task name" for="edit-task-name" name="name" required span="full">
            <input type="text" id="edit-task-name" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Details" for="edit-task-desc" name="description" span="full"
                             hint="Start a line with * for a main item, or - for a sub-item.">
            <textarea id="edit-task-desc" rows="4" class="axis-input" maxlength="10000"></textarea>
        </x-axis.drawer.field>
        {{-- Options are filled by openTaskEdit() so a former employee still on the task stays selectable --}}
        <x-axis.drawer.field label="Assignee" for="edit-task-handler" name="emp_id" required>
            <select id="edit-task-handler" class="axis-input" required></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Assigned by" for="edit-task-assigned-by" name="assigned_by_id" hint="Only leaders can assign tasks.">
            <select id="edit-task-assigned-by" class="axis-input"></select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="edit-task-status" name="status">
            <select id="edit-task-status" class="axis-input" onchange="syncTaskEditProgressFromStatus(this.value)">
                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Priority" for="edit-task-priority" name="priority">
            <select id="edit-task-priority" class="axis-input">
                @foreach (\App\Enums\TaskPriority::cases() as $taskPriority)
                    <option value="{{ $taskPriority->value }}">{{ $taskPriority->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Progress (%)" for="edit-task-progress" name="progress" span="full"
                             hint="Up to two decimals, e.g. 15.35. Follows the status: 0% is Not yet started, 100% is Finished.">
            <input type="number" id="edit-task-progress" min="0" max="100" step="0.01" inputmode="decimal" class="axis-input sm:max-w-[200px]"
                   oninput="syncTaskEditStatusFromProgress(this.value)">
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <x-axis.drawer.section title="Timing">
        <x-axis.drawer.field label="Due: start date → deadline" for="edit-task-schedule" name="deadline" span="full"
                             hint="When the work should be finished. Drives the Deadline column (days left or late).">
            <x-axis.date-range-field id="edit-task-schedule" start-id="edit-task-start" end-id="edit-task-end" />
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Effort: estimated work hours" for="edit-task-est" name="est_hours" span="full"
                             hint="Hours of actual work, not calendar time. Compared with the task's timer.">
            <input type="number" id="edit-task-est" min="0" step="0.5" class="axis-input sm:max-w-[200px]">
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
