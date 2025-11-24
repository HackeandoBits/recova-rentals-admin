<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para agregar 'reunion'
        DB::statement("ALTER TABLE `bookings` MODIFY `meeting_type` ENUM('none', 'virtual', 'whatsapp', 'in_person', 'reunion') NOT NULL DEFAULT 'none'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original
        DB::statement("ALTER TABLE `bookings` MODIFY `meeting_type` ENUM('none', 'virtual', 'whatsapp', 'in_person') NOT NULL DEFAULT 'none'");
    }
};
