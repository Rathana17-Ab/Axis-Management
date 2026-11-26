@extends('layouts.axis')

@section('content')
    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-[300] flex flex-col gap-2 pointer-events-none"></div>

    @include('axis.partials.login-modal')

    <!-- ===================================================================== -->
    <!-- MAIN AUTHENTICATED WORKSPACE (Opens Immediately)                      -->
    <!-- ===================================================================== -->
    <div class="flex-1 flex h-full w-full overflow-hidden">

        @include('axis.partials.sidebar')

        <!-- RIGHT MAIN AREA -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            @include('axis.partials.header')

            <!-- SCROLLABLE PAGE VIEWPORT -->
            <main id="axis-main" class="flex-1 overflow-y-auto p-5">

                @include('axis.pages.dashboard')
                @include('axis.pages.projects')
                @include('axis.pages.modules')
                @include('axis.pages.task-detail')
                @include('axis.pages.reports')
                @include('axis.pages.team')
                @include('axis.pages.roles')
                @include('axis.pages.work-queues')
                @include('axis.pages.sharing')
                @include('axis.pages.p18')

            </main>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODALS & SLIDE-OVER DRAWERS                                           -->
    <!-- ===================================================================== -->
    @include('axis.partials.command-palette')

    {{-- Universal drawers (resources/views/components/axis/drawer.blade.php) --}}
    @include('axis.drawers.project-create')
    @include('axis.drawers.axis-project-edit')
    @include('axis.drawers.add-project')
    @include('axis.drawers.project-view')
    @include('axis.drawers.project-edit')
    @include('axis.drawers.create-task')
    @include('axis.drawers.task-edit')
    @include('axis.drawers.dev-issue')
    @include('axis.drawers.report')
    @include('axis.drawers.report-view')
    @include('axis.drawers.team-member')
    @include('axis.drawers.team-member-edit')
    @include('axis.drawers.team-member-password')
    @include('axis.drawers.department-edit')
    @include('axis.drawers.position-edit')
    @include('axis.drawers.confirm')

    {{-- Dialogs that are not CRUD drawers --}}
    @include('axis.modals.department-create')
    @include('axis.modals.position-create')
    @include('axis.modals.sound-lab')
@endsection

@push('scripts')
    <script>
        window.AxisBootstrap = @json($bootstrap);
    </script>
    <script src="{{ asset('js/axis-workspace.js') }}?v={{ filemtime(public_path('js/axis-workspace.js')) }}"></script>
@endpush
