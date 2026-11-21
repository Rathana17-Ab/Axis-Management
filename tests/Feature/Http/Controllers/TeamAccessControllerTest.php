<?php

use App\Enums\EmployeeStatus;
use App\Models\User;

describe('store', function () {
    it('adds a team member with login access', function () {
        $this->postJson(route('admin.api.team.store'), [
            'name' => 'New Engineer',
            'job_title' => 'QA Engineer',
            'department' => 'Quality',
            'position' => 'developer',
            'email' => 'qa@eocambo.dev',
        ])
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.initials', 'NE')
            ->assertJsonMissingPath('data.password');

        expect(User::firstWhere('email', 'qa@eocambo.dev')->can_login)->toBeTrue();
    });

    it('returns 422 for an email that is already registered', function () {
        User::factory()->create(['email' => 'qa@eocambo.dev']);

        $this->postJson(route('admin.api.team.store'), [
            'name' => 'Dup',
            'job_title' => 'QA',
            'position' => 'developer',
            'email' => 'qa@eocambo.dev',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    });
});

it('marks an employee inactive and stores their description', function () {
    $member = User::factory()->create();

    $this->patchJson(route('admin.api.team.update', $member), [
        'status' => 'inactive',
        'description' => 'Moved to the finance team',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.description', 'Moved to the finance team');

    expect($member->fresh()->status)->toBe(EmployeeStatus::Inactive);
});

it('updates an employee department and position', function () {
    $member = User::factory()->create();

    $this->patchJson(route('admin.api.team.update', $member), ['department' => 'Engineering', 'position' => 'leader'])
        ->assertOk()
        ->assertJsonPath('data.department', 'Engineering')
        ->assertJsonPath('data.position', 'leader')
        ->assertJsonMissingPath('data.role');

    expect($member->fresh()->isLeader())->toBeTrue();
});

it('returns 422 for an unknown employee status', function () {
    $this->patchJson(route('admin.api.team.update', User::factory()->create()), ['status' => 'retired'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('locks and re-enables a member login', function () {
    $member = User::factory()->create();

    $this->patchJson(route('admin.api.team.toggle-login', $member))->assertOk()->assertJsonPath('data.active', false);
    $this->patchJson(route('admin.api.team.toggle-login', $member))->assertOk()->assertJsonPath('data.active', true);
});

describe('session switch', function () {
    it('signs in as the selected member and returns a fresh csrf token', function () {
        $member = User::factory()->create(['email' => 'titong@eocambo.dev']);

        $this->postJson(route('admin.api.session.switch'), ['email' => 'TiTong@eocambo.dev'])
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonStructure(['csrf_token']);

        $this->assertAuthenticatedAs($member);
    });

    it('returns 423 for a locked member', function () {
        User::factory()->locked()->create(['email' => 'maco@eocambo.dev']);

        $this->postJson(route('admin.api.session.switch'), ['email' => 'maco@eocambo.dev'])
            ->assertStatus(423)
            ->assertJsonPath('message', 'This account is locked. Ask an admin to turn sign-in back on.');

        $this->assertGuest();
    });

    it('returns 422 for an unknown email', function () {
        $this->postJson(route('admin.api.session.switch'), ['email' => 'ghost@eocambo.dev'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "We couldn't find anyone with that email.");

        $this->assertGuest();
    });

    it('returns 422 when a provided password is wrong', function () {
        User::factory()->create(['email' => 'rathana@eocambo.dev', 'password' => 'correct-password']);

        $this->postJson(route('admin.api.session.switch'), ['email' => 'rathana@eocambo.dev', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "That password doesn't look right.");

        $this->assertGuest();
    });
});
