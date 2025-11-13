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
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // Polimórfico manual (compatible con morphTo)
            $table->string('product_type');            // App\Models\Item | App\Models\Combo
            $table->unsignedBigInteger('product_id');

            // Snapshot
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index(['product_type', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
