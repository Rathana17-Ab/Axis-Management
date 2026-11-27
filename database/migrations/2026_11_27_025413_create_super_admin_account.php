<?php

use App\Enums\WorkspaceRole;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Create the shared super-admin account on deployed databases.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        (new SuperAdminSeeder)->run();
    }

    /**
     * Remove the shared super-admin account and its dedicated role.
     */
    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('slug', WorkspaceRole::SuperAdmin->value)
            ->value('id');

        $userId = DB::table('users')
            ->where('email', Str::lower(trim((string) config('super_admin.email'))))
            ->value('id');

        if ($roleId !== null && $userId !== null) {
            DB::table('role_user')
                ->where('role_id', $roleId)
                ->where('user_id', $userId)
                ->delete();
        }

        if ($roleId !== null && ! DB::table('role_user')->where('role_id', $roleId)->exists()) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        if ($userId !== null) {
            DB::table('users')->where('id', $userId)->delete();
        }
    }
};
