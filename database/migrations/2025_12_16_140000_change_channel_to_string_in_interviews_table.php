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
        Schema::table('interviews', function (Blueprint $table) {
            // Change enum to string to allow 'google_calendar' and flexible values
            $table->string('channel')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot easily revert to enum with data validation without raw SQL
        // Typically we leave it as string or try to revert if possible
        /*
        Schema::table('interviews', function (Blueprint $table) {
            $table->enum('channel', [
                'office',
                'whatsapp',
                'email',
                'virtual_meeting',
                'physical_meeting',
            ])->default('office')->change();
        });
        */
    }
};
