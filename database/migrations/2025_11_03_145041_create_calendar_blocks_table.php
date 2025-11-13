<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Bloqueo de agenda');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_all_day')->default(false);
            $table->enum('kind', ['manual', 'mantenimiento', 'feriado', 'otro'])->default('manual');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('owner_user_id')->default((int) env('OWNER_CAL_USER_ID', 1));

            // Sincronización con Google Calendar
            $table->string('google_event_id')->nullable()->index();
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();

            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // === Índices optimizados ===
            // Reemplaza el antiguo (starts_at, ends_at)
            $table->index(['owner_user_id', 'starts_at'], 'calendar_blocks_owner_starts_idx');
            $table->index(['owner_user_id', 'ends_at'], 'calendar_blocks_owner_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');
    }
};
