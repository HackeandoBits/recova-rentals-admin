<?php

namespace App\Console\Commands;

use App\Models\CalendarBlock;
use Illuminate\Console\Command;

class DiagnoseBlocks extends Command
{
    protected $signature = 'blocks:diagnose';
    protected $description = 'Diagnostica bloques de calendario creados recientemente';

    public function handle()
    {
        $this->info('=== Diagnóstico de Bloques ===');

        // Total de bloques
        $total = CalendarBlock::count();
        $this->info("Total de bloques en BD: {$total}");

        // Últimos 10 bloques
        $recent = CalendarBlock::latest()->take(10)->get();

        $this->info("\nÚltimos 10 bloques creados:");
        $this->table(
            ['ID', 'Título', 'Inicio', 'Todo el día', 'Sync Status', 'Google ID', 'Error'],
            $recent->map(fn($b) => [
                $b->id,
                $b->title,
                $b->starts_at->format('Y-m-d H:i'),
                $b->is_all_day ? 'Sí' : 'No',
                $b->sync_status,
                $b->google_event_id ? 'Sí' : 'No',
                $b->last_error ? substr($b->last_error, 0, 30) . '...' : '-'
            ])
        );

        // Bloques pendientes de sincronización
        $pending = CalendarBlock::where('sync_status', 'pending')->count();
        $this->warn("Bloques pendientes de sync: {$pending}");

        // Bloques fallidos
        $failed = CalendarBlock::where('sync_status', 'failed')->count();
        if ($failed > 0) {
            $this->error("Bloques con error de sync: {$failed}");
            $failedBlocks = CalendarBlock::where('sync_status', 'failed')->get();
            foreach ($failedBlocks as $fb) {
                $this->error("  - ID {$fb->id}: {$fb->last_error}");
            }
        }

        // Bloques sincronizados
        $synced = CalendarBlock::where('sync_status', 'synced')->count();
        $this->info("Bloques sincronizados: {$synced}");

        return 0;
    }
}
