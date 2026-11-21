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
        Schema::create('sod_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->unique();
            $table->string('label');
            $table->string('archetype')->index();
            $table->string('permitted_pages')->default('1');
            $table->boolean('can_approve')->default(false);
            $table->text('approves_summary')->nullable();
            $table->text('creates_summary')->nullable();
            $table->text('sod_rules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sod_roles');
    }
};
