<?php

namespace App\Console\Commands;

use App\Models\CalendarBlock;
use App\Models\GoogleToken;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class DebugSync extends Command
{
    protected $signature = 'debug:sync';

    protected $description = 'Debug Google Calendar Sync';

    public function handle()
    {
        $this->info('Queue Connection: '.config('queue.default'));
        $ownerId = config('owner.calendar_user_id', 1);
        $this->info('Owner ID: '.$ownerId);

        $token = GoogleToken::where('user_id', $ownerId)->first();

        if ($token) {
            $this->info("Token found for owner (ID: $ownerId).");
            $this->info('Access Token: '.substr($token->access_token, 0, 10).'...');
        } else {
            $this->error("ERROR: No GoogleToken found for owner (ID: $ownerId).");

            return;
        }

        $block = CalendarBlock::first();
        if ($block) {
            $this->info('Found Block ID: '.$block->id);
            $this->info('Sync Status: '.$block->sync_status);

            $this->info('Attempting manual sync...');
            try {
                $service = new GoogleCalendarService;
                $event = $service->upsertBlock($block);

                if ($event) {
                    $this->info('Sync SUCCESS! Event ID: '.$event->id);
                } else {
                    $this->error('Sync FAILED (returned null).');
                    $this->error('Last Error in DB: '.$block->fresh()->last_error);
                }
            } catch (\Throwable $e) {
                $this->error('EXCEPTION: '.$e->getMessage());
                $this->error($e->getTraceAsString());
            }
        } else {
            $this->warn('No CalendarBlock found to test.');
        }
    }
}
