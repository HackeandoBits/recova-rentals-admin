<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Added for DB facade usage

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename booking_items to interview_items
        if (Schema::hasTable('booking_items')) {
            Schema::rename('booking_items', 'interview_items');
        }

        // 2. Add columns to interviews
        Schema::table('interviews', function (Blueprint $table) {
            if (! Schema::hasColumn('interviews', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('id');
                $table->string('customer_email')->nullable()->after('customer_name');
                $table->string('customer_phone')->nullable()->after('customer_email');
                $table->date('event_date')->nullable()->after('end_at');
                $table->string('service_type', 120)->nullable()->after('event_date');
                $table->text('order_notes')->nullable()->after('service_type');
            }
        });

        // 3. Migrate data if possible (Basic attempt)
        // We need to move customer data from bookings to interviews
        $bookings = DB::table('bookings')->get();
        foreach ($bookings as $booking) {
            // Find associated interview(s)
            $interviews = DB::table('interviews')->where('booking_id', $booking->id)->get();

            foreach ($interviews as $interview) {
                DB::table('interviews')->where('id', $interview->id)->update([
                    'customer_name' => $booking->customer_name,
                    'customer_email' => $booking->customer_email,
                    'customer_phone' => $booking->customer_phone,
                    'event_date' => $booking->event_date,
                    'service_type' => $booking->service_type,
                    'order_notes' => $booking->notes,
                ]);
            }

            // If there are items for this booking, we need to link them to an interview.
            // If multiple interviews exist, we pick the first one? Or duplicate?
            // For simplicity, we pick the first one. If no interview exists, we lose the link (or should create one?)
            // Assuming 1:1 or 1:N where items belong to the "Order" which is now the Interview.

            $firstInterview = $interviews->first();
            if ($firstInterview) {
                DB::table('interview_items')->where('booking_id', $booking->id)->update([
                    'booking_id' => $firstInterview->id, // We will rename this column later
                ]);
            }
        }

        // 4. Update interview_items structure
        Schema::table('interview_items', function (Blueprint $table) {
            // We need to change the foreign key.
            // First, drop the old FK if it exists.
            // The FK name usually stays as 'booking_items_booking_id_foreign' even after rename
            $table->dropForeign('booking_items_booking_id_foreign');

            // Rename column booking_id to interview_id
            $table->renameColumn('booking_id', 'interview_id');
        });

        Schema::table('interview_items', function (Blueprint $table) {
            // Add new FK
            $table->foreign('interview_id')->references('id')->on('interviews')->cascadeOnDelete();
        });

        // 5. Drop bookings table and cleanup interviews
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });

        Schema::dropIfExists('bookings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreating bookings table would be complex and lossy if we don't backup.
        // For now, we just reverse the structure changes roughly.

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->date('event_date')->nullable();
            $table->enum('meeting_type', ['none', 'virtual', 'whatsapp', 'in_person'])->default('none');
            $table->date('meeting_date')->nullable();
            $table->string('meeting_time_note')->nullable();
            $table->string('service_type', 120)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dropColumn(['customer_name', 'customer_email', 'customer_phone', 'event_date', 'service_type', 'order_notes']);
        });

        Schema::table('interview_items', function (Blueprint $table) {
            $table->dropForeign(['interview_id']);
            $table->renameColumn('interview_id', 'booking_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::rename('interview_items', 'booking_items');
    }
};
