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
        Schema::create('task_issue_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->string('category')->default('Bug / Blocker');
            $table->enum('severity', ['Critical', 'High', 'Medium', 'Low'])->default('High');
            $table->string('git_branch')->nullable();
            $table->enum('status', ['Open', 'Investigating', 'Resolved'])->default('Open');
            $table->text('encountered');
            $table->text('resolution')->nullable();
            $table->string('author_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_issue_notes');
    }
};
