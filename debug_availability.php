<?php

use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel (since we will run this with php artisan tinker or similar, but better to just use script runner)
// Actually we can run this via `php artisan tinker debug_availability.php` if we structure it right, or just paste it.
// Better: Create a command or just a raw script requiring bootstrap.

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- START DEBUG ---\n";

try {
    // 1. Create Pending Interview
    $tomorrow = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);
    $dateStr = $tomorrow->format('Y-m-d');

    echo "Creating Pending Interview for $tomorrow\n";

    $interview = Interview::create([
        'title' => 'DEBUG PENDING',
        'start_at' => $tomorrow,
        'end_at' => $tomorrow->copy()->addHour(),
        'status' => 'pending',
        'channel' => 'physical_meeting',
        'customer_name' => 'Debug User',
        'customer_email' => 'debug@example.com',
        'customer_phone' => '00000000',
    ]);

    echo 'Interview Created: ID '.$interview->id.' Status: '.$interview->status."\n";

    // 2. Run Logic from BookingController::getOccupiedTimeSlots
    $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
    $date = Carbon::parse($dateStr, $tz);

    echo 'Checking availability for date: '.$date->format('Y-m-d').' (Timezone: '.$date->timezone->getName().")\n";

    $startOfDayUtc = $date->copy()->startOfDay()->setTimezone('UTC');
    $endOfDayUtc = $date->copy()->endOfDay()->setTimezone('UTC');

    echo 'Query Range (UTC): '.$startOfDayUtc->toDateTimeString().' to '.$endOfDayUtc->toDateTimeString()."\n";

    // Enable Query Log
    DB::enableQueryLog();

    $interviews = Interview::where('status', '!=', 'cancelled')
        ->where(function ($q) use ($startOfDayUtc, $endOfDayUtc) {
            $q->whereBetween('start_at', [$startOfDayUtc, $endOfDayUtc])
                ->orWhereBetween('end_at', [$startOfDayUtc, $endOfDayUtc])
                ->orWhere(function ($q2) use ($startOfDayUtc, $endOfDayUtc) {
                    $q2->where('start_at', '<', $startOfDayUtc)
                        ->where('end_at', '>', $endOfDayUtc);
                });
        })
        ->get();

    echo 'Query Executed: '.json_encode(DB::getQueryLog())."\n";
    echo 'Found '.$interviews->count()." interviews.\n";

    $found = false;
    foreach ($interviews as $i) {
        if ($i->id === $interview->id) {
            $found = true;
            echo "MATCH: Found our pending interview!\n";
            // Check slot calculation
            $meetingStart = Carbon::parse($i->start_at)->setTimezone($tz);
            echo 'Meeting Local Start: '.$meetingStart->format('Y-m-d H:i')."\n";
            if ($meetingStart->isSameDay($date)) {
                echo 'Slot BLOCKED: '.$meetingStart->format('H:i')."\n";
            } else {
                echo "Slot NOT BLOCKED (Day Mismatch)\n";
            }
        }
    }

    if (! $found) {
        echo "FAIL: Did not find the pending interview in the query results.\n";
    }

} catch (\Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getTraceAsString();
} finally {
    if (isset($interview)) {
        $interview->forceDelete();
        echo "Cleanup: Deleted interview.\n";
    }
}

echo "--- END DEBUG ---\n";
