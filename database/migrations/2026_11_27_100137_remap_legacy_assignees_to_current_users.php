<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point stale task assignees and module teams at current active users.
     */
    public function up(): void
    {
        $activeDeveloperIds = DB::table('users')
            ->where('status', 'active')
            ->where('position', 'developer')
            ->orderBy('id')
            ->pluck('id');

        if ($activeDeveloperIds->isEmpty()) {
            return;
        }

        $activeLeaderIds = DB::table('users')
            ->where('status', 'active')
            ->where('position', 'leader')
            ->orderBy('id')
            ->pluck('id');

        $fallbackDeveloperId = $activeDeveloperIds->first();
        $fallbackDeveloperName = (string) DB::table('users')->where('id', $fallbackDeveloperId)->value('name');

        DB::table('project_tasks')
            ->whereNull('emp_id')
            ->orWhereNotIn('emp_id', $activeDeveloperIds)
            ->update(['emp_id' => $fallbackDeveloperId]);

        $staleAssigners = DB::table('project_tasks')
            ->whereNotNull('assigned_by_id')
            ->whereNotIn('assigned_by_id', $activeLeaderIds);

        $staleAssigners->update([
            'assigned_by_id' => $activeLeaderIds->first(),
        ]);

        $activeDeveloperNames = DB::table('users')
            ->where('status', 'active')
            ->where('position', 'developer')
            ->orderBy('id')
            ->pluck('name');

        DB::table('modules')->select('id', 'team_members')->orderBy('id')->each(function (object $module) use ($activeDeveloperNames, $fallbackDeveloperName): void {
            $storedTeam = json_decode((string) $module->team_members, true);
            $team = is_array($storedTeam) ? $storedTeam : [];

            $normalizedTeam = array_values(array_unique(array_map(
                fn (mixed $name): string => is_string($name) && $activeDeveloperNames->contains($name)
                    ? $name
                    : $fallbackDeveloperName,
                $team,
            )));

            DB::table('modules')->where('id', $module->id)->update([
                'team_members' => json_encode($normalizedTeam),
            ]);
        });
    }

    /**
     * The original stale IDs cannot be recovered after this data cleanup.
     */
    public function down(): void
    {
        //
    }
};
