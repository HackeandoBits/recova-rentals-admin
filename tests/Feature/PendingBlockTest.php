<?php

namespace Tests\Feature;

use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_interview_blocks_time_slot()
    {
        // 1. Arrange: Create a pending interview for tomorrow at 10:00 AM
        $tomorrow = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);

        $interview = Interview::create([
            'title' => 'Test Pending Interview',
            'start_at' => $tomorrow,
            'end_at' => $tomorrow->copy()->addHour(),
            'status' => 'pending', // Explicitly pending
            'channel' => 'physical_meeting',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '12345678',
        ]);

        // 2. Act: Call the API
        $response = $this->getJson('/api/v1/bookings/occupied-time-slots?date='.$tomorrow->toDateString());

        // 3. Assert: 10:00 should be in blocked_slots
        $response->assertStatus(200);
        $response->assertJsonFragment(['blocked_slots']);

        $blockedSlots = $response->json('blocked_slots');

        // Clean up
        $interview->forceDelete();

        $this->assertTrue(in_array('10:00', $blockedSlots), '10:00 slot provided by pending interview was NOT found in blocked_slots. Found: '.implode(', ', $blockedSlots));
    }
}
