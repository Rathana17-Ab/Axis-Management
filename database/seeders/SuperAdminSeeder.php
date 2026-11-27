<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the shared super-admin account and grants it every workspace permission.
 */
class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = collect(WorkspacePermission::cases())
            ->mapWithKeys(fn (WorkspacePermission $permission): array => [
                $permission->value => Permission::query()->firstOrCreate(
                    ['key' => $permission->value],
                    ['label' => $permission->label()],
                ),
            ]);

        $superAdminRole = Role::query()->firstOrCreate(
            ['slug' => WorkspaceRole::SuperAdmin->value],
            [
                'label' => WorkspaceRole::SuperAdmin->label(),
                'description' => 'Full unrestricted workspace access.',
            ],
        );

        $superAdminRole->permissions()->sync($permissions->values()->pluck('id')->all());

        $email = Str::lower(trim((string) config('super_admin.email')));

        $superAdmin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('super_admin.name'),
                'job_title' => 'Workspace Administrator',
                'position' => EmployeePosition::Leader,
                'can_login' => true,
                'password' => config('super_admin.password'),
            ],
        );

        $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
    }
}
