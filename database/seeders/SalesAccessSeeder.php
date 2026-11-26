<?php

namespace Database\Seeders;

use App\Enums\FeatureUpgradeState;
use App\Enums\QueueAccess;
use App\Enums\RecordShareAbility;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\CtoReport;
use App\Models\FeatureUpgrade;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Project;
use App\Models\RecordShare;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Database\Seeder;

/**
 * Seeds the role matrix, demo teams/queues and P18 states used by the Sales Admin and Sales Person flows.
 */
class SalesAccessSeeder extends Seeder
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

        $adminRole = Role::query()->firstOrCreate(
            ['slug' => WorkspaceRole::SalesAdmin->value],
            ['label' => WorkspaceRole::SalesAdmin->label(), 'description' => 'Full administration and record access.'],
        );
        $salesRole = Role::query()->firstOrCreate(
            ['slug' => WorkspaceRole::SalesPerson->value],
            ['label' => WorkspaceRole::SalesPerson->label(), 'description' => 'Access to shared records and assigned queues.'],
        );

        $adminRole->permissions()->sync($permissions->values()->pluck('id')->all());
        $salesRole->permissions()->sync([
            $permissions[WorkspacePermission::P18View->value]->id,
            $permissions[WorkspacePermission::RecordsViewShared->value]->id,
            $permissions[WorkspacePermission::QueuesViewShared->value]->id,
        ]);

        $members = User::query()->orderBy('id')->get();
        $adminRole->users()->sync($members->take(1)->pluck('id')->all());
        $salesRole->users()->sync($members->skip(1)->pluck('id')->all());

        $salesTeam = Team::query()->firstOrCreate(
            ['name' => 'Axis Sales'],
            ['description' => 'Shared pipeline and customer work.'],
        );
        $deliveryTeam = Team::query()->firstOrCreate(
            ['name' => 'Delivery'],
            ['description' => 'Implementation and support hand-off.'],
        );
        $salesTeam->users()->sync($members->pluck('id')->all());

        $queue = WorkQueue::query()->firstOrCreate(
            ['name' => 'Shared pipeline'],
            [
                'description' => 'Customer records and follow-ups shared across sales.',
                'assignment_rule' => 'Shared with the Axis Sales team',
                'team_id' => $salesTeam->id,
                'owner_id' => $members->first()?->id,
                'is_active' => true,
            ],
        );
        $queue->users()->syncWithPivotValues($members->skip(1)->pluck('id')->all(), ['access' => QueueAccess::Viewer->value]);

        WorkQueue::query()->firstOrCreate(
            ['name' => 'Escalations'],
            [
                'description' => 'Delivery issues that need an owner.',
                'assignment_rule' => 'Assigned by the Sales Admin',
                'team_id' => $deliveryTeam->id,
                'owner_id' => $members->first()?->id,
                'is_active' => true,
            ],
        );

        $module = Module::query()->orderBy('id')->first();
        $project = Project::query()->orderBy('id')->first();
        $report = CtoReport::query()->orderBy('id')->first();
        $salesUser = $members->skip(1)->first();

        if ($salesUser !== null) {
            foreach (array_filter([$module, $project, $report]) as $record) {
                RecordShare::query()->firstOrCreate([
                    'shareable_type' => $record->getMorphClass(),
                    'shareable_id' => $record->id,
                    'user_id' => $salesUser->id,
                    'ability' => RecordShareAbility::View->value,
                ]);
            }
        }

        foreach ([
            ['p18-access-upgrade', 'Access & Upgrade States', 'Role-based feature access and upgrade controls.', FeatureUpgradeState::Upgraded],
            ['record-sharing', 'Record sharing', 'Share projects, modules and reports with Sales People.', FeatureUpgradeState::Available],
            ['work-queues', 'Work queues', 'Shared and assigned queues for the sales team.', FeatureUpgradeState::Available],
        ] as [$key, $label, $description, $state]) {
            FeatureUpgrade::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $label, 'description' => $description, 'state' => $state->value],
            );
        }
    }
}
