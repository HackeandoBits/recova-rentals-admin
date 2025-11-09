<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('calendar_blocks', function (Blueprint $table) {
            // Usa el nombre exacto que ves en SHOW INDEX / tu GUI
            $table->dropIndex('calendar_blocks_starts_at_ends_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_blocks', function (Blueprint $table) {
            $table->index(['starts_at', 'ends_at'], 'calendar_blocks_starts_at_ends_at_index');
        });
    }
};
