<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD entity: Roles (approved ERD revision — ERD §2.19).
     *
     * Stores the assignable access roles (FR-02). `is_system` marks a
     * protected built-in role (only `Administrator`), which cannot be
     * renamed or deleted. Each role's access restrictions live in the
     * `role_permissions` child table (ERD §2.20).
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('role_id');
            $table->string('role_name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
