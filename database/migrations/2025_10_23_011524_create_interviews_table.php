<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            // Campos base de Interview
            $table->id();
            $table->string('title')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Estado (usamos el de Interview, que es más completo para tu flujo)
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');

            // --- Campos fusionados de Appointment ---

            // Vínculo a la reserva (pedido) del cliente
            // Lo hacemos nullable() y nullOnDelete() para que puedas crear reuniones
            // internas (sin cliente) y para que no se borre la reunión si se borra el pedido.
            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            // Staff interno responsable (mismo caso que booking_id)
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Canal (agregué los que mencionaste antes)
            $table->enum('channel', [
                'office',
                'whatsapp',
                'email',
                'virtual_meeting',
                'physical_meeting',
            ])->default('office');

            // Nota opcional (ej: "Link de Meet: ...", "Sala 2")
            $table->string('location_note')->nullable();

            // --- Campo de Sincronización (de tu modelo) ---
            $table->string('google_event_id')->nullable()->index();

            // Timestamps
            $table->timestamps();

            // --- Índices (combinación de ambos) ---
            $table->index(['assigned_user_id', 'start_at', 'end_at'], 'interviews_owner_time_idx');
            $table->index(['status', 'start_at']);
            $table->index('start_at');
            $table->index('end_at');
        });

        // Constraint de BBDD (buena idea de tu migración de appointment)
        // Se asegura que 'ends_at' sea siempre mayor que 'start_at'
        try {
            DB::statement('
                ALTER TABLE interviews
                ADD CONSTRAINT chk_interview_time CHECK (start_at < end_at)
            ');
        } catch (\Throwable $e) {
            // Ignorar si el motor de BBDD no lo soporta (ej. MariaDB antiguo)
            // Tu modelo ya lo valida, esto es solo una capa extra.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
