<?php

use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

describe('directory links', function () {
    it('links a new member to the selected department and position', function () {
        $department = Department::factory()->create(['name' => 'Quality']);
        $position = Position::factory()->create(['name' => 'QA Lead', 'is_leader' => true]);

        $this->postJson(route('admin.api.team.store'), [
            'name' => 'New Lead',
            'job_title' => 'QA Lead',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'email' => 'new-lead@eocambo.dev',
        ])
            ->assertCreated()
            ->assertJsonPath('data.departmentId', $department->id)
            ->assertJsonPath('data.departmentName', 'Quality')
            ->assertJsonPath('data.positionId', $position->id)
            ->assertJsonPath('data.positionName', 'QA Lead')
            ->assertJsonPath('data.position', 'leader');

        $this->assertDatabaseHas('users', [
            'email' => 'new-lead@eocambo.dev',
            'department_id' => $department->id,
            'department' => 'Quality',
            'position_id' => $position->id,
            'position' => 'leader',
        ]);
    });

    it('returns 422 for an unknown directory id', function () {
        $this->postJson(route('admin.api.team.store'), [
            'name' => 'Bad Link',
            'job_title' => 'Developer',
            'department_id' => 999999,
            'position_id' => 999999,
            'email' => 'bad-link@eocambo.dev',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id', 'position_id']);
    });

    it('updates a member directory links from the selected ids', function () {
        $department = Department::factory()->create(['name' => 'Platform']);
        $position = Position::factory()->create(['name' => 'Engineer', 'is_leader' => false]);
        $member = User::factory()->create();

        $this->patchJson(route('admin.api.team.update', $member), [
            'department_id' => $department->id,
            'position_id' => $position->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.departmentName', 'Platform')
            ->assertJsonPath('data.positionName', 'Engineer');

        expect($member->fresh())
            ->department_id->toBe($department->id)
            ->position_id->toBe($position->id);
    });

    it('can unlink a member from a department', function () {
        $department = Department::factory()->create(['name' => 'Quality']);
        $member = User::factory()->create([
            'department' => 'Quality',
            'department_id' => $department->id,
        ]);

        $this->patchJson(route('admin.api.team.update', $member), ['department_id' => null])
            ->assertOk()
            ->assertJsonPath('data.departmentId', null)
            ->assertJsonPath('data.department', null);

        expect($member->fresh()->department)->toBeNull();
    });
});

describe('delete', function () {
    it('removes a member from the team directory', function () {
        $department = Department::factory()->create(['name' => 'Quality']);
        $position = Position::factory()->create(['name' => 'Reviewer']);
        $member = User::factory()->create([
            'department' => 'Quality',
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $this->deleteJson(route('admin.api.team.destroy', $member))
            ->assertOk()
            ->assertJsonPath('data.departmentId', $department->id)
            ->assertJsonPath('data.positionId', $position->id);

        $this->assertModelMissing($member);
    });

    it('refuses to remove the signed-in account', function () {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->deleteJson(route('admin.api.team.destroy', $member))
            ->assertStatus(409)
            ->assertJsonPath('message', 'You cannot remove the account you are signed in as.');

        $this->assertModelExists($member);
    });
});

describe('sign-in details', function () {
    it('sets a hashed password without returning it', function () {
        $member = User::factory()->create(['email' => 'dev@eocambo.dev']);
        $password = 'NewPassword123!';

        $response = $this->patchJson(route('admin.api.team.password', $member), [
            'password' => $password,
            'password_confirmation' => $password,
        ])
            ->assertOk()
            ->assertJsonPath('data.username', 'dev@eocambo.dev')
            ->assertJsonPath('data.active', true)
            ->assertJsonMissingPath('password');

        expect($response->getContent())->not->toContain($password);
        expect(Hash::check($password, $member->fresh()->password))->toBeTrue();
    });

    it('returns 422 when the password confirmation does not match', function () {
        $member = User::factory()->create();

        $this->patchJson(route('admin.api.team.password', $member), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'Different123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    });

    it('creates a login account for a member without one', function () {
        $member = User::factory()->create(['email' => null, 'can_login' => false]);

        $this->patchJson(route('admin.api.team.password', $member), [
            'email' => 'assignee@eocambo.dev',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.username', 'assignee@eocambo.dev');

        expect($member->fresh())
            ->email->toBe('assignee@eocambo.dev')
            ->can_login->toBeTrue();
    });

    it('requires an email when setting up a login for a member without one', function () {
        $member = User::factory()->create(['email' => null]);

        $this->patchJson(route('admin.api.team.password', $member), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });
});
