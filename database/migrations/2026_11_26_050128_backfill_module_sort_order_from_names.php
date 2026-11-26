<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Start the new custom order from the alphabetical order the Modules page already showed.
     */
    public function up(): void
    {
        DB::table('modules')->orderBy('name')->orderBy('id')->get(['id'])
            ->each(function (object $module, int $index): void {
                DB::table('modules')->where('id', $module->id)->update(['sort_order' => $index + 1]);
            });
    }

    /**
     * Restore the historical id-based order.
     */
    public function down(): void
    {
        DB::table('modules')->orderBy('id')->get(['id'])
            ->each(function (object $module, int $index): void {
                DB::table('modules')->where('id', $module->id)->update(['sort_order' => $index + 1]);
            });
    }
};
