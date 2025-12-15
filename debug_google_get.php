<?php

use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debug: Testing Google Calendar GET Event...\n";

try {
    $service = app(GoogleCalendarService::class);
    
    if (! $service->isConnected()) {
        echo "FAIL: Not connected (No Token).\n";
        exit(1);
    }

    echo "Status: Connected.\n";

    // 1. List one event to get a valid ID
    $start = Carbon::now()->startOfMonth();
    $end = Carbon::now()->endOfMonth();
    $events = $service->listEvents($start, $end);

    if (empty($events)) {
        echo "WARN: No events found in this month to test with.\n";
        exit(0);
    }

    // Pick the first one
    $testEvent = $events[0];
    $id = $testEvent->getId();
    echo "Found Event: " . $testEvent->getSummary() . " (ID: $id)\n";

    // 2. Try to GET it explicitly using the service method (which swallows errors)
    echo "Attempting service->getEvent($id)...\n";
    $result = $service->getEvent($id);
    
    if ($result) {
        echo "PASS: service->getEvent returned the event correctly.\n";
    } else {
        echo "FAIL: service->getEvent returned NULL.\n";

        // 3. Try Raw GET to see the exception
        echo "Attempting RAW API GET to see error...\n";
        try {
            $raw = $service->forOwner()->events->get('primary', $id);
            echo "PASS (Raw): API call worked! Why did service fail?\n";
        } catch (\Throwable $e) {
            echo "FAIL (Raw): API Error: " . $e->getMessage() . "\n";
        }
    }

} catch (\Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
