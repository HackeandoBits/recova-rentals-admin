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
        // $this->onQueue('google-sync'); // Removed to use default queue
    }

    public $tries = 3;                          // reintentos

    public $backoff = [10, 30, 90];             // backoff escalonado (segundos)

    public function handle(GoogleCalendarService $google): void
    {
        $block = CalendarBlock::withTrashed()->find($this->blockId);
        if (! $block) {
            return;
        }

        try {
            if ($this->delete || $block->canceled_at) {
                try {
                    $google->deleteBlockEvent($block);
                } catch (\Google\Service\Exception $e) {
                    if ($e->getCode() == 404) {
                        // Si no existe en Google, perfecto, ya está borrado.
                        \Illuminate\Support\Facades\Log::info("SyncSingleBlockJob: Evento {$block->google_event_id} no encontrado en Google (404), ignorando error.");
                        return;
                    }
                    throw $e; // Otros errores sí los reportamos
                }
            } else {
                $google->upsertBlock($block);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("SyncSingleBlockJob Failed for Block {$this->blockId}: " . $e->getMessage());
            
            // Si es un borrado, no deberíamos fallar la transacción local por error de API
            // Si es upsert, tal vez sí queremos saber que falló, pero para Bulk Operations es mejor logging.
            // Para mantener compatibilidad con queues, podríamos re-throw si no es delete.
            // Pero como usamos dispatchSync en observers, mejor silenciar y loguear.
        }
    }
}
