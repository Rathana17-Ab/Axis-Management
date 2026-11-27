<?php

use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists departments with their member counts', function () {
    $department = Department::factory()->create(['name' => 'Quality']);
    User::factory()->create([
        'department' => 'Quality',
        'department_id' => $department->id,
    ]);

    $this->getJson(route('admin.api.departments.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Engineering')
        ->assertJsonFragment(['name' => 'Quality', 'memberCount' => 1]);
});

it('creates a department', function () {
    $this->postJson(route('admin.api.departments.store'), [
        'name' => 'Customer Support',
        'description' => 'Handles customer requests',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Customer Support')
        ->assertJsonPath('data.memberCount', 0);

    $this->assertDatabaseHas('departments', ['name' => 'Customer Support']);
});

it('returns 422 for a duplicate department name', function () {
    Department::factory()->create(['name' => 'Quality']);

    $this->postJson(route('admin.api.departments.store'), ['name' => 'Quality'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('renames a department and updates linked member labels', function () {
    $department = Department::factory()->create(['name' => 'Quality']);
    $member = User::factory()->create([
        'department' => 'Quality',
        'department_id' => $department->id,
    ]);

    $this->patchJson(route('admin.api.departments.update', $department), ['name' => 'Quality Assurance'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Quality Assurance');

    expect($member->fresh()->department)->toBe('Quality Assurance');
});

it('returns 409 when deleting a department that still has members', function () {
    $department = Department::factory()->create();
    User::factory()->create(['department_id' => $department->id]);

    $this->deleteJson(route('admin.api.departments.destroy', $department))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Move or unlink this department\'s team members before deleting it.');

    $this->assertModelExists($department);
});

it('deletes an empty department', function () {
    $department = Department::factory()->create();

    $this->deleteJson(route('admin.api.departments.destroy', $department))->assertNoContent();

    $this->assertModelMissing($department);
});
