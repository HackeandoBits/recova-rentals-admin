<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['start_at']);
            $table->index(['end_at']);
            $table->index(['status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
