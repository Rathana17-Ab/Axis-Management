<x-axis.drawer id="modal-add-project" mode="create" icon="fa-folder-plus"
               breadcrumb="Projects" title="New module"
               description="Add a module to a project. You can add tasks and teammates right after."
               submit="handleAddProject(event)" submit-label="Create module">
    <x-axis.drawer.section title="Module" description="What you're building and who it's for.">
        <x-axis.drawer.field label="Project" for="add-proj-project" name="project_id" required span="full">
            <select id="add-proj-project" class="axis-input" required>
                @foreach ($axisProjects as $axisProject)
                    <option value="{{ $axisProject->id }}">{{ $axisProject->name }}</option>
                @endforeach
            </select>
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Module name" for="add-proj-name" name="name" required span="full">
            <input type="text" id="add-proj-name" placeholder="e.g. Axis Spa & Wellness" class="axis-input" required maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Description" for="add-proj-desc" name="description" span="full">
            <textarea id="add-proj-desc" rows="3" placeholder="What this module does" class="axis-input" maxlength="5000"></textarea>
        </x-axis.drawer.field>
    </x-axis.drawer.section>

    <x-axis.drawer.section title="Planning">
        <x-axis.drawer.field label="Start date" for="add-proj-start" name="start_date">
            <input type="date" id="add-proj-start" value="{{ now()->toDateString() }}" class="axis-input">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Deadline" for="add-proj-deadline" name="deadline" required>
            <input type="date" id="add-proj-deadline" value="{{ now()->addMonths(2)->endOfMonth()->toDateString() }}" class="axis-input" required>
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
