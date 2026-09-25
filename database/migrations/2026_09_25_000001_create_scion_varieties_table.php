<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revised ERD entity: Scion_Variety.
     * The single retained scion-variety master (replaces the old `varieties`
     * and generic `scions` tables). Archivable via `archived_at`.
     */
    public function up(): void
    {
        Schema::create('scion_varieties', function (Blueprint $table) {
            $table->bigIncrements('scion_variety_id');
            $table->string('scion_variety_name');
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scion_varieties');
    }
};
