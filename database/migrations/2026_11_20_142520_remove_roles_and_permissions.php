<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The system no longer uses Hotel PMS roles & permissions (SoD matrix):
 * employees are just the CTO sheet's fields, so drop the role table and the user role column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sod_role_code');
        });

        Schema::dropIfExists('sod_roles');
    }

    /**
     * Reverse the migrations (structure only; role rows are not restored).
     */
    public function down(): void
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

        Schema::table('users', function (Blueprint $table) {
            $table->string('sod_role_code')->default('ROLE-FRONT-DESK');
        });
    }
};
