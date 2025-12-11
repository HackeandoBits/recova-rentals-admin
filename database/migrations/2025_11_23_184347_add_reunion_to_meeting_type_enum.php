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
        // Para SQL Server, necesitamos manejar el CHECK constraint manualmente
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            // 1. Encontrar y eliminar el constraint CHECK existente
            $constraintName = DB::select("
                SELECT name 
                FROM sys.check_constraints 
                WHERE parent_object_id = OBJECT_ID('bookings') 
                AND COL_NAME(parent_object_id, parent_column_id) = 'meeting_type'
            ");

            if (! empty($constraintName)) {
                DB::statement("ALTER TABLE bookings DROP CONSTRAINT [{$constraintName[0]->name}]");
            }

            // 2. Modificar la columna (sin constraint)
            DB::statement('ALTER TABLE bookings ALTER COLUMN meeting_type NVARCHAR(255) NOT NULL');

            // 3. Agregar el nuevo constraint CHECK
            DB::statement("
                ALTER TABLE bookings 
                ADD CONSTRAINT CK_bookings_meeting_type 
                CHECK (meeting_type IN ('none', 'virtual', 'whatsapp', 'in_person', 'reunion'))
            ");

            // 4. Establecer el default
            DB::statement("
                ALTER TABLE bookings 
                ADD CONSTRAINT DF_bookings_meeting_type 
                DEFAULT 'none' FOR meeting_type
            ");
        } else {
            // Para MySQL u otros drivers
            Schema::table('bookings', function (Blueprint $table) {
                $table->enum('meeting_type', ['none', 'virtual', 'whatsapp', 'in_person', 'reunion'])
                    ->default('none')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            // 1. Eliminar constraints
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS CK_bookings_meeting_type');
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS DF_bookings_meeting_type');

            // 2. Modificar la columna
            DB::statement('ALTER TABLE bookings ALTER COLUMN meeting_type NVARCHAR(255) NOT NULL');

            // 3. Restaurar el constraint CHECK original
            DB::statement("
                ALTER TABLE bookings 
                ADD CONSTRAINT CK_bookings_meeting_type 
                CHECK (meeting_type IN ('none', 'virtual', 'whatsapp', 'in_person'))
            ");

            // 4. Restaurar el default
            DB::statement("
                ALTER TABLE bookings 
                ADD CONSTRAINT DF_bookings_meeting_type 
                DEFAULT 'none' FOR meeting_type
            ");
        } else {
            Schema::table('bookings', function (Blueprint $table) {
                $table->enum('meeting_type', ['none', 'virtual', 'whatsapp', 'in_person'])
                    ->default('none')
                    ->change();
            });
        }
    }
};
