<x-axis.drawer id="modal-create-task" mode="create" icon="fa-list-check" size="lg"
               breadcrumb="Tasks" title="New task"
               description="Add a piece of work to a module and assign it to someone."
               submit="handleCreateTask(event)" submit-label="Create task">
    <x-axis.drawer.section title="Task">
        <x-axis.drawer.field label="Module" for="new-task-module" name="module" required span="full" hint="The task is added to this module's task list.">
            <select id="new-task-module" class="axis-input" required>
                @foreach ($projects->sortBy('code') as $moduleOption)
                    <option value="{{ $moduleOption->id }}">{{ $moduleOption->code }} — {{ $moduleOption->name }}{{ $moduleOption->project ? ' ('.$moduleOption->project->name.')' : '' }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Task name" for="new-task-name" name="name" required span="full">
            <input type="text" id="new-task-name" placeholder="e.g. Forgot password page" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Details" for="new-task-desc" name="description" required span="full"
                             hint="Start a line with * for a main item, or - for a sub-item.">
            <textarea id="new-task-desc" rows="4" placeholder="*Implement controller endpoint&#10;- Add unit tests" class="axis-input" required></textarea>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Assignee" for="new-task-handler" name="emp_id" required hint="Any active team member, including leaders, can be assigned tasks.">
            <select id="new-task-handler" class="axis-input" required>
                @foreach ($assignees as $employee)
                    <option value="{{ $employee->id }}" @selected($employee->id === $currentUser?->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Assigned by" for="new-task-assigned-by" name="assigned_by_id" hint="Only leaders can assign tasks.">
            <select id="new-task-assigned-by" class="axis-input">
                <option value="">Not recorded</option>
                @foreach ($leaders as $leader)
                    <option value="{{ $leader->id }}" @selected($leader->id === $currentUser?->id)>{{ $leader->name }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Status" for="new-task-status" name="status">
            <select id="new-task-status" class="axis-input">
                @foreach (\App\Enums\WorkStatus::cases() as $workStatus)
                    <option value="{{ $workStatus->value }}">{{ $workStatus->value }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Priority" for="new-task-priority" name="priority">
            <select id="new-task-priority" class="axis-input">
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <x-axis.drawer.section title="Timing" description="Both are filled in for you. Change them only if you know better.">
        <x-axis.drawer.field label="Due: start date → deadline" for="new-task-schedule" name="deadline" span="full"
                             hint="When the work should be finished. Drives the Deadline column (days left or late).">
            <x-axis.date-range-field id="new-task-schedule" start-id="new-task-start" end-id="new-task-end"
                                     :start="now()->toDateString()" :end="now()->addWeeks(2)->toDateString()" />
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Effort: estimated work hours" for="new-task-est" name="est_hours" span="full"
                             hint="Hours of actual work, not calendar time. Compared with the task's timer.">
            <input type="number" id="new-task-est" value="12" min="0" step="0.5" class="axis-input sm:max-w-[200px]">
        </x-axis.drawer.field>
        <input type="hidden" id="new-task-progress" value="0">
    </x-axis.drawer.section>

    <x-axis.drawer.section title="Ran into an issue?" description="Optional. Log it now and it'll show up in the task's notes.">
        <x-axis.drawer.field label="What went wrong" for="new-task-issue-enc" name="initial_issue.encountered" span="full">
            <input type="text" id="new-task-issue-enc" placeholder="e.g. Safari submits the form twice" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="How you fixed it" for="new-task-issue-fix" name="initial_issue.resolution" span="full">
            <input type="text" id="new-task-issue-fix" placeholder="Leave blank if you're still on it" class="axis-input">
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
