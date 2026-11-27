<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the status, icon, value, title and visual hooks keyed by the card key', function () {
    $view = $this->blade(
        '<x-axis.kpi-card key="proj-active" accent="cyan" icon="fa-briefcase" status="Live" :live="true" title="Active projects" />'
    );

    $view->assertSee('class="axis-kpi axis-kpi--cyan"', false)
        ->assertSee('aria-labelledby="kpi-proj-active-title"', false)
        ->assertSee('id="kpi-proj-active-status"', false)
        ->assertSee('axis-kpi__badge-dot axis-kpi__badge-dot--live', false)
        ->assertSee('fa-solid fa-briefcase', false)
        ->assertSee('id="kpi-proj-active" class="axis-kpi__value" data-kpi-value', false)
        ->assertSee('id="kpi-proj-active-visual"', false)
        ->assertSee('id="kpi-proj-active-footer"', false)
        ->assertSeeInOrder(['Active projects', 'data-kpi-value', 'Live'], false);
});

it('renders a placeholder instead of a hardcoded value before live data arrives', function () {
    $this->blade('<x-axis.kpi-card key="k" icon="fa-eye" status="Active" title="Projects you can see" />')
        ->assertSee('data-kpi-value>—</div>', false);
});

it('only pulses the status dot for live cards', function () {
    $this->blade('<x-axis.kpi-card key="k" icon="fa-eye" status="Avg" title="Average progress" />')
        ->assertDontSee('axis-kpi__badge-dot--live', false);
});

it('shows the four executive KPI cards on the modules page', function () {
    User::factory()->create();

    $this->get(route('admin.modules.index'))
        ->assertOk()
        ->assertSee('id="project-kpi-strip"', false)
        ->assertSeeInOrder(['Active modules', 'Modules you can see', 'Average progress', 'People on modules'])
        ->assertSee('axis-kpi--cyan', false)
        ->assertSee('axis-kpi--emerald', false)
        ->assertSee('axis-kpi--violet', false)
        ->assertSee('axis-kpi--amber', false);
});

it('shows the project KPI cards on the projects page', function () {
    User::factory()->create();

    $this->get(route('admin.projects.index'))
        ->assertOk()
        ->assertSee('id="axis-project-kpi-strip"', false)
        ->assertSeeInOrder(['Projects', 'Modules', 'Average progress', 'Next deadline']);
});
