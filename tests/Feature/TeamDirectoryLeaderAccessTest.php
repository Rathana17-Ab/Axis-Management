<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\User;

it('keeps team directory lists readable for a non-leader', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.api.departments.index'))->assertOk();
    $this->getJson(route('admin.api.positions.index'))->assertOk();
});

it('refuses team member changes from a non-leader', function () {
    $this->actingAs(User::factory()->create());

    $member = User::factory()->create();
    $password = 'NewPassword123!';

    $requests = [
        fn () => $this->postJson(route('admin.api.team.store'), [
            'name' => 'Another Person',
            'job_title' => 'Developer',
            'position' => 'developer',
            'email' => 'another@eocambo.dev',
            'password' => $password,
            'password_confirmation' => $password,
        ]),
        fn () => $this->patchJson(route('admin.api.team.update', $member), ['status' => 'inactive']),
        fn () => $this->patchJson(route('admin.api.team.toggle-login', $member)),
        fn () => $this->patchJson(route('admin.api.team.password', $member), [
            'password' => $password,
            'password_confirmation' => $password,
        ]),
        fn () => $this->deleteJson(route('admin.api.team.destroy', $member)),
    ];

    foreach ($requests as $request) {
        $request()
            ->assertForbidden()
            ->assertJsonPath('message', 'Only team leaders can manage the team directory.');
    }

    $this->assertModelExists($member);
    $this->assertDatabaseMissing('users', ['email' => 'another@eocambo.dev']);
});

it('refuses department changes from a non-leader', function () {
    $this->actingAs(User::factory()->create());

    $department = Department::factory()->create();

    $this->postJson(route('admin.api.departments.store'), ['name' => 'New Department'])
        ->assertForbidden();
    $this->patchJson(route('admin.api.departments.update', $department), ['name' => 'Renamed Department'])
        ->assertForbidden();
    $this->deleteJson(route('admin.api.departments.destroy', $department))
        ->assertForbidden();

    $this->assertDatabaseMissing('departments', ['name' => 'New Department']);
    $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => $department->name]);
});

it('refuses position changes from a non-leader', function () {
    $this->actingAs(User::factory()->create());

    $position = Position::factory()->create();

    $this->postJson(route('admin.api.positions.store'), ['name' => 'New Position'])
        ->assertForbidden();
    $this->patchJson(route('admin.api.positions.update', $position), ['name' => 'Renamed Position'])
        ->assertForbidden();
    $this->deleteJson(route('admin.api.positions.destroy', $position))
        ->assertForbidden();

    $this->assertDatabaseMissing('positions', ['name' => 'New Position']);
    $this->assertDatabaseHas('positions', ['id' => $position->id, 'name' => $position->name]);
});
