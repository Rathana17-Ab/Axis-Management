<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cto_reports', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('handler_name')
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('cto_reports')
            ->whereNull('user_id')
            ->orderBy('id')
            ->get(['id', 'handler_name'])
            ->each(function (object $report): void {
                $userId = DB::table('users')
                    ->where('name', $report->handler_name)
                    ->value('id');

                if ($userId !== null) {
                    DB::table('cto_reports')->where('id', $report->id)->update(['user_id' => $userId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cto_reports', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
