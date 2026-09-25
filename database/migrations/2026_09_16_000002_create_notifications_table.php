<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the Notifications entity (ERD §2.19, approved revision §9.5).
     *
     * Persistent, per-user in-application notifications backing the navbar
     * bell. Rows are written by one centralized writer
     * (App\Models\Notification::record()) from real operational actions, never
     * fabricated (RULE 5/12).
     *
     * The source ERD does not mark Batch_ID as NOT NULL for the similar
     * Activity_Log entity; non-batch operations (task completion, report
     * generation) have no batch, so batch_id is nullable here too. The
     * (user_id, is_read) index serves the unread badge/count.
     *
     * Notifications are transient per-recipient alerts rather than
     * authoritative operational history, so deleting a user cascades to their
     * own notifications (this also avoids a hidden FK failure in FR-02 user
     * deletion, which already blocks users with real operational records).
     * Batch_id stays restrictOnDelete: it is only ever set for operational
     * events on batches that cannot be deleted anyway.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('notification_id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('batches', 'batch_id')
                ->restrictOnDelete();
            $table->string('activity_type');
            $table->string('title');
            $table->string('message');
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();
            $table->datetime('created_at');
            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
