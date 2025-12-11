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
        // Modificar el ENUM para agregar 'reunion'
        // Laravel maneja automáticamente la diferencia entre MySQL (ENUM) y SQL Server (VARCHAR + CHECK constraint)
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('meeting_type', ['none', 'virtual', 'whatsapp', 'in_person', 'reunion'])
                  ->default('none')
                  ->change();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('meeting_type', ['none', 'virtual', 'whatsapp', 'in_person'])
                  ->default('none')
                  ->change();
        });
    }
};