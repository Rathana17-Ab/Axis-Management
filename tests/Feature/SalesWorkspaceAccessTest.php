<?php

use App\Enums\FeatureUpgradeState;
use App\Enums\QueueAccess;
use App\Enums\RecordShareAbility;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\FeatureUpgrade;
use App\Models\Module;
use App\Models\Project;
use App\Models\RecordShare;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkQueue;
use Database\Seeders\SalesAccessSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(SalesAccessSeeder::class);
});

/**
 * Create a signed-in member with one of the seeded workspace roles.
 */
function workspaceMemberWithRole(WorkspaceRole $role): User
{
    $user = User::factory()->create();
    $roleModel = Role::query()->where('slug', $role->value)->firstOrFail();

    $user->roles()->attach($roleModel);

    return $user;
}

it('grants Sales Admin every workspace permission and Sales Person only the sales view permissions', function () {
    $admin = workspaceMemberWithRole(WorkspaceRole::SalesAdmin);
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $legacy = User::factory()->create();

    foreach (WorkspacePermission::cases() as $permission) {
        expect(Gate::forUser($admin)->allows($permission->value))->toBeTrue();
    }

    $salesPermissions = [
        WorkspacePermission::P18View,
        WorkspacePermission::RecordsViewShared,
        WorkspacePermission::QueuesViewShared,
    ];

    foreach (WorkspacePermission::cases() as $permission) {
        expect(Gate::forUser($sales)->allows($permission->value))
            ->toBe(in_array($permission, $salesPermissions, true), $permission->value);
    }

    foreach (WorkspacePermission::cases() as $permission) {
        expect(Gate::forUser($legacy)->allows($permission->value))->toBeTrue();
    }
});

it('opens each new workspace page for an authorized Sales Admin', function (string $routeName, string $page) {
    $admin = workspaceMemberWithRole(WorkspaceRole::SalesAdmin);

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertViewHas('initialPage', $page)
        ->assertSee('id="page-'.$page.'"', false);
})->with([
    'administration' => ['admin.roles.index', 'roles'],
    'work queues' => ['admin.work-queues.index', 'work-queues'],
    'record sharing' => ['admin.sharing.index', 'sharing'],
    'P18' => ['admin.p18.index', 'p18'],
]);

it('lets Sales Admin create and persist administration records', function () {
    $admin = workspaceMemberWithRole(WorkspaceRole::SalesAdmin);

    $teamDescription = trim(str_repeat('Handles renewals ', 20));
    $queueDescription = trim(str_repeat('Upcoming renewals ', 20));

    $teamResponse = $this->actingAs($admin)->postJson(route('admin.api.teams.store'), [
        'name' => 'Sales Support',
        'description' => $teamDescription,
    ])->assertCreated()->assertJsonPath('data.name', 'Sales Support');

    $queueResponse = $this->actingAs($admin)->postJson(route('admin.api.work-queues.store'), [
        'name' => 'Renewals',
        'description' => $queueDescription,
        'is_active' => true,
    ])->assertCreated()->assertJsonPath('data.name', 'Renewals');

    $project = Project::factory()->create(['name' => 'Renewal project']);
    $shareResponse = $this->actingAs($admin)->postJson(route('admin.api.record-shares.store'), [
        'shareable_type' => 'project',
        'shareable_id' => $project->id,
        'user_id' => $admin->id,
        'ability' => RecordShareAbility::Update->value,
    ])->assertCreated()->assertJsonPath('data.shareableId', $project->id);

    $upgrade = FeatureUpgrade::query()->firstOrFail();

    $this->actingAs($admin)
        ->patchJson(route('admin.api.feature-upgrades.update', $upgrade), ['state' => FeatureUpgradeState::Locked->value])
        ->assertOk()
        ->assertJsonPath('data.state', FeatureUpgradeState::Locked->value);

    expect($teamResponse->json('data.id'))->toBeInt()
        ->and($queueResponse->json('data.ownerId'))->toBe($admin->id)
        ->and($shareResponse->json('data.userId'))->toBe($admin->id);

    $this->assertDatabaseHas('teams', ['name' => 'Sales Support', 'description' => $teamDescription])
        ->assertDatabaseHas('work_queues', ['name' => 'Renewals', 'description' => $queueDescription, 'owner_id' => $admin->id])
        ->assertDatabaseHas('record_shares', ['shareable_id' => $project->id, 'user_id' => $admin->id])
        ->assertDatabaseHas('feature_upgrades', ['id' => $upgrade->id, 'state' => FeatureUpgradeState::Locked->value]);
});

it('lets Sales Person open the sales and team pages but forbids administration pages', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);

    foreach (['admin.work-queues.index', 'admin.sharing.index', 'admin.p18.index'] as $routeName) {
        $this->actingAs($sales)->get(route($routeName))->assertOk();
    }

    $this->actingAs($sales)->get(route('admin.team.index'))
        ->assertOk()
        ->assertSee('id="nav-team"', false)
        ->assertSee('id="page-team"', false);

    $this->actingAs($sales)->get(route('admin.roles.index'))->assertForbidden();
});

it('hydrates the workspace with permission-aware client flags', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);

    $this->actingAs($sales)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('bootstrap', function (array $bootstrap): bool {
            return $bootstrap['isSignedIn'] === true
                && $bootstrap['roleKeys'] === [WorkspaceRole::SalesPerson->value]
                && in_array(WorkspacePermission::P18View->value, $bootstrap['permissions'], true)
                && ! $bootstrap['canAccessAdministration']
                && $bootstrap['canViewWorkQueues']
                && $bootstrap['canViewSharing']
                && $bootstrap['canViewP18']
                && ! $bootstrap['canManageWorkQueues']
                && ! $bootstrap['canManageRecords']
                && ! $bootstrap['canManageP18']
                && $bootstrap['permissionLabels'][WorkspacePermission::P18View->value] === 'View access & upgrade states'
                && $bootstrap['upgradeStateLabels'][FeatureUpgradeState::Locked->value] === 'Restricted';
        });
});

it('hides administration flags from guests in the open console', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="nav-team"', false)
        ->assertViewHas('bootstrap', function (array $bootstrap): bool {
            return $bootstrap['isSignedIn'] === false
                && $bootstrap['roleKeys'] === []
                && $bootstrap['permissions'] === []
                && ! $bootstrap['canAccessAdministration']
                && ! $bootstrap['canViewWorkQueues']
                && ! $bootstrap['canViewSharing']
                && ! $bootstrap['canViewP18'];
        });
});

it('forbids Sales Person from changing administration records', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $team = Team::query()->firstOrFail();
    $upgrade = FeatureUpgrade::query()->firstOrFail();

    $this->actingAs($sales)->postJson(route('admin.api.teams.store'), ['name' => 'Unauthorized team'])->assertForbidden();
    $this->actingAs($sales)->postJson(route('admin.api.work-queues.store'), ['name' => 'Unauthorized queue'])->assertForbidden();
    $this->actingAs($sales)->postJson(route('admin.api.record-shares.store'), [
        'shareable_type' => 'project',
        'shareable_id' => Project::factory()->create()->id,
        'user_id' => $sales->id,
        'ability' => RecordShareAbility::View->value,
    ])->assertForbidden();
    $this->actingAs($sales)->patchJson(route('admin.api.feature-upgrades.update', $upgrade), ['state' => 'upgraded'])->assertForbidden();
    $this->actingAs($sales)->deleteJson(route('admin.api.teams.destroy', $team))->assertForbidden();

    $this->assertDatabaseMissing('teams', ['name' => 'Unauthorized team'])
        ->assertDatabaseMissing('work_queues', ['name' => 'Unauthorized queue'])
        ->assertDatabaseHas('feature_upgrades', ['id' => $upgrade->id, 'state' => $upgrade->state->value]);
});

it('limits Sales Person queues to queues they own or are shared with', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $sharedQueue = WorkQueue::factory()->create(['name' => 'Shared queue']);
    $hiddenQueue = WorkQueue::factory()->create(['name' => 'Hidden queue']);
    $sharedQueue->users()->attach($sales, ['access' => QueueAccess::Viewer->value]);

    $response = $this->actingAs($sales)->getJson(route('admin.api.work-queues.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Shared queue')
        ->assertJsonPath('data.0.access', QueueAccess::Viewer->value);

    expect(collect($response->json('data'))->pluck('name')->all())->not->toContain('Hidden queue');
});

it('limits Sales Person record shares to records shared with them', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $other = User::factory()->create();
    $sharedProject = Project::factory()->create(['name' => 'Visible project']);
    $hiddenProject = Project::factory()->create(['name' => 'Hidden project']);

    RecordShare::factory()->create([
        'shareable_type' => $sharedProject->getMorphClass(),
        'shareable_id' => $sharedProject->id,
        'user_id' => $sales->id,
        'ability' => RecordShareAbility::View->value,
    ]);
    RecordShare::factory()->create([
        'shareable_type' => $hiddenProject->getMorphClass(),
        'shareable_id' => $hiddenProject->id,
        'user_id' => $other->id,
        'ability' => RecordShareAbility::View->value,
    ]);

    $response = $this->actingAs($sales)->getJson(route('admin.api.record-shares.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Visible project')
        ->assertJsonPath('canManage', false);

    expect($response->json('records'))->toBeEmpty()
        ->and($response->json('users'))->toBeEmpty();
});

it('grants view access to a module through a shared parent project', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $project = Project::factory()->create(['name' => 'Shared parent']);
    $module = Module::factory()->for($project)->create(['name' => 'Customer module']);

    RecordShare::factory()->create([
        'shareable_type' => $project->getMorphClass(),
        'shareable_id' => $project->id,
        'user_id' => $sales->id,
        'ability' => RecordShareAbility::View->value,
    ]);

    $this->actingAs($sales)
        ->get(route('admin.projects.tasks', $module))
        ->assertOk()
        ->assertViewHas('bootstrap', fn (array $bootstrap): bool => collect($bootstrap['projects'])->contains('id', $module->id));
});

it('denies access to an unshared module and blocks updates without the update ability', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $project = Project::factory()->create(['name' => 'Private parent']);
    $module = Module::factory()->for($project)->create(['name' => 'Private module']);

    $this->actingAs($sales)->get(route('admin.projects.tasks', $module))->assertForbidden();
    $this->actingAs($sales)
        ->patchJson(route('admin.api.modules.update', $module), ['name' => 'Changed without access'])
        ->assertForbidden();

    $this->assertDatabaseHas('modules', ['id' => $module->id, 'name' => 'Private module'])
        ->assertDatabaseMissing('modules', ['name' => 'Changed without access']);
});

it('allows updates through an update share and persists the change', function () {
    $sales = workspaceMemberWithRole(WorkspaceRole::SalesPerson);
    $module = Module::factory()->create(['name' => 'Shared module']);

    RecordShare::factory()->create([
        'shareable_type' => $module->getMorphClass(),
        'shareable_id' => $module->id,
        'user_id' => $sales->id,
        'ability' => RecordShareAbility::Update->value,
    ]);

    $this->actingAs($sales)
        ->patchJson(route('admin.api.modules.update', $module), ['name' => 'Updated by Sales Person'])
        ->assertOk();

    $this->assertDatabaseHas('modules', ['id' => $module->id, 'name' => 'Updated by Sales Person']);
});

it('rejects duplicate record shares without creating a second row', function () {
    $admin = workspaceMemberWithRole(WorkspaceRole::SalesAdmin);
    $project = Project::factory()->create();
    $payload = [
        'shareable_type' => 'project',
        'shareable_id' => $project->id,
        'user_id' => $admin->id,
        'ability' => RecordShareAbility::View->value,
    ];

    $this->actingAs($admin)->postJson(route('admin.api.record-shares.store'), $payload)->assertCreated();
    $this->actingAs($admin)->postJson(route('admin.api.record-shares.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This record is already shared with that person.');

    expect(RecordShare::query()->where('shareable_id', $project->id)->where('user_id', $admin->id)->count())->toBe(1);
});

it('validates administration payloads for authorized admins', function () {
    $admin = workspaceMemberWithRole(WorkspaceRole::SalesAdmin);

    $this->actingAs($admin)->postJson(route('admin.api.teams.store'), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($admin)->postJson(route('admin.api.work-queues.store'), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($admin)->postJson(route('admin.api.record-shares.store'), [
        'shareable_type' => 'unknown',
        'shareable_id' => 1,
        'user_id' => $admin->id,
        'ability' => 'unknown',
    ])->assertUnprocessable()->assertJsonValidationErrors(['shareable_type', 'ability']);
});

it('requires a signed-in session for the new workspace administration endpoints', function () {
    $this->get(route('admin.dashboard'))->assertOk();

    foreach (['admin.roles.index', 'admin.work-queues.index', 'admin.sharing.index', 'admin.p18.index'] as $routeName) {
        $this->get(route($routeName))->assertUnauthorized();
    }

    $this->postJson(route('admin.api.work-queues.store'), ['name' => 'Anonymous queue'])->assertUnauthorized();
    $this->getJson(route('admin.api.record-shares.index'))->assertUnauthorized();

    $this->assertDatabaseMissing('work_queues', ['name' => 'Anonymous queue']);
});
