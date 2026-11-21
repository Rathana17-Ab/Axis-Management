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
        Schema::create('cto_reports', function (Blueprint $table) {
            $table->id();
            $table->enum('report_type', ['Daily', 'Weekly'])->default('Daily');
            $table->date('report_date');
            $table->string('handler_name')->index();
            $table->string('module_name');
            $table->text('summary');
            $table->string('blocker')->default('None');
            $table->decimal('hours_logged', 5, 2)->default(8.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cto_reports');
    }
};
