<?php

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Role;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Seed the super-admin account and return it.
 */
function seededSuperAdmin(): User
{
    (new SuperAdminSeeder)->run();

    return User::query()
        ->where('email', Str::lower(trim((string) config('super_admin.email'))))
        ->firstOrFail();
}

it('creates an active super admin with every workspace permission', function () {
    $superAdmin = seededSuperAdmin();
    $role = Role::query()->where('slug', WorkspaceRole::SuperAdmin->value)->firstOrFail();

    expect($superAdmin->can_login)->toBeTrue()
        ->and($superAdmin->name)->toBe(config('super_admin.name'))
        ->and($role->label)->toBe('Super Admin')
        ->and($superAdmin->roles()->whereKey($role->id)->exists())->toBeTrue();

    foreach (WorkspacePermission::cases() as $permission) {
        expect(Gate::forUser($superAdmin)->allows($permission->value))->toBeTrue();
    }
});

it('lets the seeded super admin sign in and out', function () {
    $superAdmin = seededSuperAdmin();

    $this->post(route('login.store'), [
        'email' => config('super_admin.email'),
        'password' => config('super_admin.password'),
    ])->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($superAdmin);

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does not duplicate the super admin when seeded repeatedly', function () {
    seededSuperAdmin();
    seededSuperAdmin();

    $email = Str::lower(trim((string) config('super_admin.email')));

    expect(User::query()->where('email', $email)->count())->toBe(1)
        ->and(Role::query()->where('slug', WorkspaceRole::SuperAdmin->value)->count())->toBe(1);
});
