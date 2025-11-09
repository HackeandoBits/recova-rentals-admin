<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('calendar_blocks', function (Blueprint $table) {
            $table->index(['owner_user_id', 'starts_at'], 'calendar_blocks_owner_starts_idx');
            $table->index(['owner_user_id', 'ends_at'], 'calendar_blocks_owner_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_blocks', function (Blueprint $table) {
            $table->dropIndex('calendar_blocks_owner_starts_idx');
            $table->dropIndex('calendar_blocks_owner_ends_idx');
        });
    }
};
