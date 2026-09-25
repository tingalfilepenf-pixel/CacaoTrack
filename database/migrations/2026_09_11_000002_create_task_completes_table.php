<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD entity: Task_Complete (actual completion record).
     * User_ID = who completed it; Verified_By_ID/Verified_At = verification
     * (auto-filled on completion in this first version).
     */
    public function up(): void
    {
        Schema::create('task_completes', function (Blueprint $table) {
            $table->bigIncrements('completion_id');
            $table->foreignId('task_id')->constrained('activity_tasks', 'task_id')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('verified_by_id')->nullable()->constrained('users');
            $table->date('completion_date');
            $table->timestamp('verified_at')->nullable();
            $table->text('remark')->nullable();

            $table->unique(['task_id', 'completion_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_completes');
    }
};
