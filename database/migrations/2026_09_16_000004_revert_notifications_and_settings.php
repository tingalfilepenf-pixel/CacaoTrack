<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the Session AC "approved ERD revision".
     *
     * Notifications are delivered as transient session-flash toasts (PRD §19
     * feedback) which require no persistence, so the `notifications` table and
     * the `users.settings` attribute are removed to restore the source ERD's
     * 18-entity model (PRD §9 forbids redundant tables; RULE 4).
     *
     * Guards make this migration idempotent: it is a no-op on a freshly
     * migrated database where the two Session-AC migrations have not run, and
     * it drops the objects on the existing `db_capstone` database.
     */
    public function up(): void
    {
        Schema::dropIfExists('notifications');

        if (Schema::hasColumn('users', 'settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('settings')->nullable()->after('role');
            });
        }

        if (! Schema::hasTable('notifications')) {
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
    }
};
