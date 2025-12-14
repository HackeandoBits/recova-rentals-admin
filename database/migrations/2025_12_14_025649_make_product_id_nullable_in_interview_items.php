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
        Schema::table('interview_items', function (Blueprint $table) {
            // "product_id" usually implies unsignedBigInteger if it's a relation.
            // Using ->change() to modify existing column.
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interview_items', function (Blueprint $table) {
            // Revert to not null (requires knowing if it had default, assuming no)
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
