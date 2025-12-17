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
        // 1. Eliminar explícitamente el CHECK constraint de SQL Server
        // (Laravel a veces no encuentra el nombre generado aleatoriamente 'CK_xxxx')
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("
                DECLARE @name NVARCHAR(128)
                SELECT @name = name
                FROM sys.check_constraints
                WHERE parent_object_id = OBJECT_ID('interviews')
                AND parent_column_id = COLUMNPROPERTY(OBJECT_ID('interviews'), 'channel', 'ColumnId')

                IF @name IS NOT NULL
                BEGIN
                    EXEC('ALTER TABLE interviews DROP CONSTRAINT ' + @name)
                END
            ");
        }

        // 2. Asegurar que la columna sea string (VARCHAR)
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('channel')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No volver a poner el constraint para evitar problemas de datos
    }
};
