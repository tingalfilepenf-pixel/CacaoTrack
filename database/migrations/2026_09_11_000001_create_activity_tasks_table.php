<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Activity_Task (master task definition).
     * Note: `priority` is an explicit, approved ERD revision (the source ERD
     * defined only Task_ID, Task_Name, Description) to back the checklist UI.
     */
    public function up(): void
    {
        Schema::create('activity_tasks', function (Blueprint $table) {
            $table->bigIncrements('task_id');
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('medium');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_tasks');
    }
};
