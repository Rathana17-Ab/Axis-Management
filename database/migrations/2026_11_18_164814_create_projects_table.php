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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('proj_badge', 4)->default('A');
            $table->string('badge_class')->default('bg-sky-500/20 text-sky-300 border border-sky-400/30');
            $table->string('customer_name');
            $table->string('customer_sub')->nullable();
            $table->string('cust_badge', 4)->default('C');
            $table->string('product_domain')->index();
            $table->date('deposit_date')->nullable();
            $table->date('deadline')->nullable();
            $table->string('manager_name');
            $table->json('team_members')->nullable();
            $table->string('maint_end')->default('-');
            $table->string('closed_at_label')->default('-');
            $table->enum('status', ['In Progress', 'Completed', 'Pending', 'On Hold'])->default('In Progress');
            $table->string('overdue_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
