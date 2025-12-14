<?php

namespace App\Console\Commands;

use App\Models\CalendarBlock;
use App\Models\Interview;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class PushToGoogleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:push-to-google';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push existing future interviews and blocks to Google Calendar that are missing sync IDs.';

    /**
     * Execute the console command.
     */
    public function handle(GoogleCalendarService $google)
    {
        $this->info('Starting sync of existing data to Google Calendar...');

        // 1. Interviews
        $interviews = Interview::query()
            ->where('status', 'confirmed')
            ->whereNull('google_event_id')
            ->where('start_at', '>=', now())
            ->get();

        $this->info("Found {$interviews->count()} pending Interviews.");
        $bar = $this->output->createProgressBar($interviews->count());

        foreach ($interviews as $interview) {
            try {
                // Use Observer-like logic: upsert
                // We use upsertInterviewEventForOwner because we assume these belong to the system owner
                $eventId = $google->upsertInterviewEventForOwner($interview);

                $interview->withoutEvents(function () use ($interview, $eventId) {
                    $interview->forceFill(['google_event_id' => $eventId])->saveQuietly();
                });

                $bar->advance();
            } catch (\Throwable $e) {
                $this->error("\nFailed to sync Interview ID {$interview->id}: {$e->getMessage()}");
            }
        }
        $bar->finish();
        $this->newLine(2);

        // 2. Calendar Blocks
        $blocks = CalendarBlock::query()
            ->whereNull('canceled_at')
            ->whereNull('google_event_id')
            ->where('starts_at', '>=', now())
            ->get();

        $this->info("Found {$blocks->count()} pending Calendar Blocks.");
        $bar2 = $this->output->createProgressBar($blocks->count());

        foreach ($blocks as $block) {
            try {
                $google->upsertBlock($block);
                $bar2->advance();
            } catch (\Throwable $e) {
                $this->error("\nFailed to sync Block ID {$block->id}: {$e->getMessage()}");
            }
        }
        $bar2->finish();
        $this->newLine();

        $this->info('Sync complete!');
    }
}
