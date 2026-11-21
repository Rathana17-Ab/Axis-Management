<x-axis.drawer id="drawer-project-view" mode="view" icon="fa-folder-open"
               breadcrumb="PROJ-0000" title="Project details" description=""
               primary-action="openProjectEdit(viewingProjectId)" submit-label="Edit">
    <x-axis.drawer.section title="Overview">
        <dl class="contents">
            <x-axis.drawer.detail label="Customer"><span data-project-view="customer">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Product"><span data-project-view="product">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Status"><span data-project-view="status">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Lead"><span data-project-view="manager">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Description" span="full"><span data-project-view="description" class="whitespace-pre-line">—</span></x-axis.drawer.detail>
        </dl>
    </x-axis.drawer.section>
    <x-axis.drawer.section title="Schedule">
        <dl class="contents">
            <x-axis.drawer.detail label="Start date"><span data-project-view="startDate" class="font-mono">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Deadline"><span data-project-view="deadline" class="font-mono">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Support ends"><span data-project-view="maintEnd">—</span></x-axis.drawer.detail>
            <x-axis.drawer.detail label="Time left"><span data-project-view="timeLeft">—</span></x-axis.drawer.detail>
        </dl>
    </x-axis.drawer.section>
    <x-axis.drawer.section title="Progress">
        <div class="sm:col-span-2">
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-300"><span data-project-view="tasksDone">0</span> of <span data-project-view="tasksTotal">0</span> tasks done</span>
                <span data-project-view="progress" class="font-mono font-semibold text-white">0%</span>
            </div>
            <div class="h-2 rounded-full bg-white/[0.08] overflow-hidden mt-2">
                <div data-project-view="progressBar" class="h-full rounded-full bg-sky-400" style="width: 0%"></div>
            </div>
        </div>
    </x-axis.drawer.section>
    <x-axis.drawer.section title="Team">
        <div data-project-view="team" class="sm:col-span-2 flex flex-wrap gap-2"></div>
        <button type="button" onclick="openProjectTaskList(viewingProjectId); AxisDrawer.close('drawer-project-view')" class="sm:col-span-2 justify-self-start text-sm text-sky-300 hover:text-sky-200 inline-flex items-center gap-1.5">
            Open the task list <i class="fa-solid fa-arrow-right text-xs"></i>
        </button>
    </x-axis.drawer.section>
</x-axis.drawer>
