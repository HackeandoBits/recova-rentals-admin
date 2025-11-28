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
        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('report_type'); // 'monthly', 'custom', 'whatsapp'
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status')->default('sent'); // 'sent', 'failed'
            $table->json('metadata')->nullable(); // Datos adicionales del reporte
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('report_type');
        });
    }

};
