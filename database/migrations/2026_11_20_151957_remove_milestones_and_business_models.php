<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestones and business models were removed from the product: drop their tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('milestone_steps');
        Schema::dropIfExists('business_models');
    }

    /**
     * Reverse the migrations (structure only; the rows are not restored).
     */
    public function down(): void
    {
        Schema::create('business_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->default('N/A');
            $table->timestamps();
        });

        Schema::create('milestone_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(1);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }
};
