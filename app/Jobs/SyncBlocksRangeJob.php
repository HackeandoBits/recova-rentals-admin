<?php

namespace App\Jobs;

use App\Models\CalendarBlock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBlocksRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $sinceDate)
    {
        // $this->onQueue('google-sync'); // Removed to use default queue
    }

    public $tries = 3;

    public $backoff = [10, 30, 90];

    public function handle(): void
    {
        CalendarBlock::active()
            ->where('ends_at', '>=', $this->sinceDate)
            ->orderBy('id')
            ->chunkById(100, function ($blocks) {
                foreach ($blocks as $b) {
                    SyncSingleBlockJob::dispatchSync($b->id);
                }
            });
    }
}
