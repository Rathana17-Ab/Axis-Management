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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('priority', ['High', 'Medium', 'Low'])->default('High');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('handler_name')->index();
            $table->enum('status', ['In Progress', 'Completed', 'Pending', 'Review'])->default('In Progress');
            $table->decimal('progress', 5, 2)->default(0.00);
            $table->decimal('est_hours', 8, 2)->default(8.00);
            $table->unsignedBigInteger('act_seconds')->default(0);
            $table->boolean('is_running')->default(false);
            $table->timestamp('timer_started_at')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
