<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiamos la columna 'channel' de ENUM a STRING (VARCHAR)
        // Esto elimina la restriccion estricta de valores y permite 'google_calendar'
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('channel')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volvemos a ENUM (OJO: Si hay datos 'google_calendar' esto podría fallar en el rollback)
        Schema::table('interviews', function (Blueprint $table) {
            $table->enum('channel', [
                'office',
                'whatsapp',
                'email',
                'virtual_meeting',
                'physical_meeting',
            ])->default('office')->change();
        });
    }
};
