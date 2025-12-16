<?php

namespace App\Observers;

use App\Jobs\SyncSingleBlockJob;
use App\Models\CalendarBlock;

class CalendarBlockObserver
{
    public function created(CalendarBlock $block): void
    {
        // Si ya está marcado como 'synced' al crearse, significa que vino de Google. No lo re-enviamos.
        if ($block->sync_status === 'synced') {
            return;
        }

        SyncSingleBlockJob::dispatchSync($block->id);
    }

    public function updated(CalendarBlock $block): void
    {
        // Si es un feriado importado, no queremos intentar editarlo en Google (dará 404 o error de permisos)
        if ($block->kind === 'feriado') {
            return;
        }
    
        // Si estamos actualizando porque acabamos de importar (sync_status cambió a synced), no hacemos nada.
        // Pero si isDirty incluye sync_status, debemos tener cuidado.
        // Asumimos que si sync_status es 'synced', no necesitamos disparar sync.
        // Excepción: si cambiamos título y queremos pushear update.
        // Mejor: Si vino de Google, google_event_id ya existe.
        
        if ($block->isDirty(['starts_at', 'ends_at', 'is_all_day', 'title', 'reason', 'kind'])) {
             // Si es un evento externo (kind != manual/maintenance) tal vez no deberíamos editarlo en Google Primary?
             // Por ahora protegemos solo feriados.
            
            SyncSingleBlockJob::dispatchSync($block->id);
        }

        if ($block->isDirty('canceled_at') && $block->canceled_at) {
            SyncSingleBlockJob::dispatchSync($block->id, delete: true);
        }
    }

    public function deleted(CalendarBlock $block): void
    {
        SyncSingleBlockJob::dispatchSync($block->id, delete: true);
    }
}
