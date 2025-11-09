<?php

namespace App\Console\Commands;

use App\Models\CalendarBlock;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class GoogleSyncBlocks extends Command
{
    // Uso: php artisan google:sync-blocks --since=2025-11-01
    protected $signature = 'google:sync-blocks {--since= : ISO date (YYYY-MM-DD) para limitar los bloques a sincronizar}';
    protected $description = 'Sincroniza CalendarBlocks activos con el Google Calendar del OWNER (primary).';

    public function handle(GoogleCalendarService $google)
    {
        $query = CalendarBlock::active();

        if ($since = $this->option('since')) {
            $query->where('ends_at', '>=', $since);
            $this->info("Filtrando bloques con ends_at >= {$since}");
        }

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($blocks) use ($google, &$count) {
            foreach ($blocks as $b) {
                $event = $google->upsertBlock($b);
                $status = $event ? 'synced' : 'failed';
                $this->line("Block #{$b->id} → {$status}");
                $count++;
            }
        });

        $this->info("Total procesados: {$count}");
        return self::SUCCESS;
    }
}
