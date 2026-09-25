<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD entity: Role_Permissions (approved ERD revision — ERD §2.20).
     *
     * Stores each role's defined access restrictions as permission keys
     * from the code-defined catalog (App\Models\Role::PERMISSIONS, ERD
     * §9.7). Unique per role. Cascades with its role because the rows are
     * an attribute of the role itself.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->bigIncrements('role_permission_id');
            $table->foreignId('role_id')->constrained('roles', 'role_id')->cascadeOnDelete();
            $table->string('permission', 100);

            $table->unique(['role_id', 'permission']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
