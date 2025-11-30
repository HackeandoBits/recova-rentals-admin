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
        Schema::table('report_logs', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('status');
            $table->enum('report_format', ['full', 'summary'])->default('full')->after('pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_logs', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'report_format']);
        });
    }
};
