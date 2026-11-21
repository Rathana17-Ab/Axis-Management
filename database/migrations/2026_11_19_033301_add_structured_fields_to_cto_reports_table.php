<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cto_reports', function (Blueprint $table) {
            $table->string('recipient')->default('Dear Bong and team')->after('report_type');
            $table->date('date_from')->nullable()->after('report_date');
            $table->date('date_to')->nullable()->after('date_from');
            $table->json('content_json')->nullable()->after('summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cto_reports', function (Blueprint $table) {
            $table->dropColumn(['recipient', 'date_from', 'date_to', 'content_json']);
        });
    }
};
