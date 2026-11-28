<?php

use App\Enums\EmployeePosition;
use App\Models\Position;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->leader()->create());
});

it('lists positions with their member counts', function () {
    $position = Position::factory()->create(['name' => 'Support Lead']);
    User::factory()->create([
        'position' => EmployeePosition::Leader,
        'position_id' => $position->id,
    ]);

    $this->getJson(route('admin.api.positions.index'))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Support Lead', 'memberCount' => 1]);
});

it('creates a position with a generated code', function () {
    $this->postJson(route('admin.api.positions.store'), [
        'name' => 'Team Leader',
        'description' => 'Assigns work to the team',
        'is_leader' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Team Leader')
        ->assertJsonPath('data.code', 'team-leader')
        ->assertJsonPath('data.isLeader', true);

    $this->assertDatabaseHas('positions', ['name' => 'Team Leader', 'code' => 'team-leader']);
});

it('returns 422 for a duplicate position name', function () {
    Position::factory()->create(['name' => 'Architect']);

    $this->postJson(route('admin.api.positions.store'), ['name' => 'Architect'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('returns 422 for an invalid position code', function () {
    $this->postJson(route('admin.api.positions.store'), ['name' => 'Architect', 'code' => 'Not A Code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('updates a position and syncs linked members task-assignment role', function () {
    $position = Position::factory()->create(['name' => 'Reviewer', 'is_leader' => false]);
    $member = User::factory()->create([
        'position' => EmployeePosition::Developer,
        'position_id' => $position->id,
    ]);

    $this->patchJson(route('admin.api.positions.update', $position), ['is_leader' => true])
        ->assertOk()
        ->assertJsonPath('data.isLeader', true);

    expect($member->fresh()->isLeader())->toBeTrue();
});

it('returns 409 when deleting a position that still has members', function () {
    $position = Position::factory()->create();
    User::factory()->create(['position_id' => $position->id]);

    $this->deleteJson(route('admin.api.positions.destroy', $position))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Move or unlink this position\'s team members before deleting it.');

    $this->assertModelExists($position);
});

it('deletes an empty position', function () {
    $position = Position::factory()->create();

    $this->deleteJson(route('admin.api.positions.destroy', $position))->assertNoContent();

    $this->assertModelMissing($position);
});
