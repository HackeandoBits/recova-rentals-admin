<?php

namespace App\Observers;

use App\Jobs\SyncSingleBlockJob;
use App\Models\CalendarBlock;

class CalendarBlockObserver
{
    public function created(CalendarBlock $block): void
    {
        SyncSingleBlockJob::dispatch($block->id);
    }

    public function updated(CalendarBlock $block): void
    {
        if ($block->isDirty(['starts_at', 'ends_at', 'is_all_day', 'title', 'reason', 'kind'])) {
            SyncSingleBlockJob::dispatch($block->id);
        }

        if ($block->isDirty('canceled_at') && $block->canceled_at) {
            SyncSingleBlockJob::dispatch($block->id, delete: true);
        }
    }

    public function deleted(CalendarBlock $block): void
    {
        SyncSingleBlockJob::dispatch($block->id, delete: true);
    }
}
