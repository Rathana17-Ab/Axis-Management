<?php

namespace App\Providers;

use App\Enums\QueueAccess;
use App\Enums\RecordShareAbility;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ProjectTask;
use App\Models\RecordShare;
use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        foreach (WorkspacePermission::cases() as $permission) {
            Gate::define($permission->value, function (?User $user) use ($permission): bool {
                $user = Controller::currentWorkspaceUser($user);

                return $user !== null
                    && (! $user->hasWorkspaceRole() || $user->hasWorkspacePermission($permission));
            });
        }

        // Legacy users without a workspace role keep the existing open-console behavior.
        Gate::define('view-record', function (?User $user, object $record): bool {
            $user = Controller::currentWorkspaceUser($user);
            if ($user === null) {
                return false;
            }

            if (! $user->hasWorkspaceRole()) {
                return true;
            }

            if ($user->hasWorkspacePermission(WorkspacePermission::RecordsViewAll)) {
                return true;
            }

            return $this->hasViewAccess($user, $record);
        });

        Gate::define('update-record', function (?User $user, object $record): bool {
            $user = Controller::currentWorkspaceUser($user);
            if ($user === null) {
                return false;
            }

            if (! $user->hasWorkspaceRole()) {
                return true;
            }

            if ($user->hasWorkspacePermission(WorkspacePermission::RecordsManageAll)) {
                return true;
            }

            return $this->hasUpdateAccess($user, $record);
        });

        Gate::define('manage-work-queue', function (?User $user, WorkQueue $queue): bool {
            $user = Controller::currentWorkspaceUser($user);
            if ($user === null) {
                return false;
            }

            if (! $user->hasWorkspaceRole()) {
                return true;
            }

            if ($user->hasWorkspacePermission(WorkspacePermission::WorkQueuesManage)) {
                return true;
            }

            if ($queue->owner_id === $user->id) {
                return true;
            }

            return $queue->users()
                ->where('user_id', $user->id)
                ->where('access', QueueAccess::Editor->value)
                ->exists();
        });
    }

    /**
     * Whether the user has a share (optionally with a specific ability) for the record.
     */
    private function hasShare(User $user, object $record, ?RecordShareAbility $ability = null): bool
    {
        if (! method_exists($record, 'getMorphClass') || ! isset($record->id)) {
            return false;
        }

        $query = RecordShare::query()
            ->where('user_id', $user->id)
            ->where('shareable_type', $record->getMorphClass())
            ->where('shareable_id', $record->id);

        if ($ability !== null) {
            $query->where('ability', $ability->value);
        }

        return $query->exists();
    }

    /**
     * Shares on a parent record also grant visibility of its children.
     */
    private function hasViewAccess(User $user, object $record): bool
    {
        if ($this->hasShare($user, $record)) {
            return true;
        }

        if ($record instanceof Module) {
            return Module::isVisibleTo($user, $record);
        }

        if ($record instanceof ProjectTask) {
            if ($record->emp_id === $user->id) {
                return true;
            }

            $module = $record->module;
            if ($module === null) {
                return false;
            }

            return in_array($user->name, $module->team_members ?? [], true)
                || $this->hasShare($user, $module)
                || ($module->project !== null && $this->hasShare($user, $module->project));
        }

        return false;
    }

    /**
     * Update access follows the same hierarchy, but requires the update ability.
     */
    private function hasUpdateAccess(User $user, object $record): bool
    {
        if ($this->hasShare($user, $record, RecordShareAbility::Update)) {
            return true;
        }

        if ($record instanceof Module) {
            return $record->project !== null
                && $this->hasShare($user, $record->project, RecordShareAbility::Update);
        }

        if ($record instanceof ProjectTask) {
            $module = $record->module;
            if ($module === null) {
                return false;
            }

            return $this->hasShare($user, $module, RecordShareAbility::Update)
                || ($module->project !== null && $this->hasShare($user, $module->project, RecordShareAbility::Update));
        }

        return false;
    }
}
