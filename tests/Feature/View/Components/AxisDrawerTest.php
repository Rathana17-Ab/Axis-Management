<?php

use App\Models\Module;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the standard header, body and footer structure', function () {
    $view = $this->blade(
        '<x-axis.drawer id="drawer-demo" title="Create New Project" description="Set it up." breadcrumb="Projects" icon="fa-folder-plus" submit="save(event)">Body content</x-axis.drawer>'
    );

    $view->assertSee('id="drawer-demo"', false)
        ->assertSee('data-axis-drawer', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-labelledby="drawer-demo-title"', false)
        ->assertSee('<form class="axis-drawer__panel', false)
        ->assertSee('onsubmit="save(event)" novalidate', false)
        ->assertSeeInOrder(['Projects', 'Create New Project', 'Set it up.', 'Body content', 'Cancel', 'Create']);
});

it('derives primary and cancel labels from the mode', function (string $mode, string $primary, string $busy, string $cancel) {
    $view = $this->blade(
        '<x-axis.drawer id="d" title="T" :mode="$mode" primary-action="go()">x</x-axis.drawer>',
        ['mode' => $mode],
    );

    $view->assertSee('data-mode="'.$mode.'"', false)
        ->assertSee('<span data-drawer-submit-label>'.$primary.'</span>', false)
        ->assertSee('data-busy-label="'.$busy.'"', false)
        ->assertSee($cancel);
})->with([
    'create' => ['create', 'Create', 'Creating…', 'Cancel'],
    'edit' => ['edit', 'Save changes', 'Saving…', 'Cancel'],
    'view' => ['view', 'Edit', 'Opening…', 'Close'],
    'delete' => ['delete', 'Delete', 'Deleting…', 'Cancel'],
    'assign' => ['assign', 'Assign', 'Assigning…', 'Cancel'],
    'notes' => ['notes', 'Save note', 'Saving…', 'Cancel'],
]);

it('styles the primary action as destructive in delete mode', function () {
    $this->blade('<x-axis.drawer id="d" title="Delete Project" mode="delete" primary-action="go()">x</x-axis.drawer>')
        ->assertSee('axis-btn-danger', false)
        ->assertDontSee('btn-primary-glass', false);
});

it('renders a close-only footer when the drawer has no primary action', function () {
    $this->blade('<x-axis.drawer id="d" title="Role" mode="view">x</x-axis.drawer>')
        ->assertSee('Close')
        ->assertDontSee('data-drawer-submit', false);
});

it('renders without a form wrapper when no submit handler is given', function () {
    $this->blade('<x-axis.drawer id="d" title="T" primary-action="go()">x</x-axis.drawer>')
        ->assertSee('<div class="axis-drawer__panel', false)
        ->assertDontSee('<form', false);
});

it('marks required fields and renders an error slot keyed to the validation name', function () {
    $view = $this->blade(
        '<x-axis.drawer.field label="Customer" for="cust" name="customer_name" required span="full"><input id="cust"></x-axis.drawer.field>'
    );

    $view->assertSee('data-field="customer_name"', false)
        ->assertSee('for="cust"', false)
        ->assertSee('<span class="text-rose-300" aria-hidden="true">*</span>', false)
        ->assertSee('data-error-for="customer_name"', false)
        ->assertSee('sm:col-span-2', false);
});

it('omits the asterisk for optional fields', function () {
    $this->blade('<x-axis.drawer.field label="Description" name="description"><input></x-axis.drawer.field>')
        ->assertDontSee('aria-hidden="true">*', false);
});

it('renders every workspace drawer on the shared component', function () {
    User::factory()->create();
    Module::factory()->create();

    $response = $this->get(route('admin.projects.index'))->assertOk();

    foreach ([
        'drawer-project-create', 'modal-add-project', 'drawer-project-view', 'drawer-project-edit', 'modal-create-task', 'drawer-task-edit',
        'dev-issue-drawer', 'modal-report', 'modal-report-view', 'modal-team-member', 'drawer-team-member-edit', 'drawer-team-member-password',
        'drawer-department-edit', 'drawer-position-edit', 'axis-confirm-drawer', 'drawer-axis-project-edit',
    ] as $drawerId) {
        $response->assertSee('<div id="'.$drawerId.'"', false);
    }

    expect(substr_count($response->getContent(), 'data-axis-drawer'))->toBe(16);
});
