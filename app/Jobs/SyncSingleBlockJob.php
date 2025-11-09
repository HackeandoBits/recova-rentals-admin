<?php

namespace App\Jobs;

use App\Models\CalendarBlock;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSingleBlockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $blockId, public bool $delete = false)
    {
        $this->onQueue('google-sync');
    }

    public $tries = 3;                          // reintentos
    public $backoff = [10, 30, 90];             // backoff escalonado (segundos)

    public function handle(GoogleCalendarService $google): void
    {
        $block = CalendarBlock::withTrashed()->find($this->blockId);
        if (!$block) return;

        if ($this->delete || $block->canceled_at) {
            $google->deleteBlockEvent($block);
        } else {
            $google->upsertBlock($block);
        }
    }
}
