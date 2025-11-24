<?php

namespace App\Observers;

use App\Models\Interview;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Log;

class InterviewObserver
{
    /** Ejecutar después del commit para evitar eventos duplicados. */
    public bool $afterCommit = true;

    public function created(Interview $i): void
    {
        $this->syncUpsert($i);
    }

    public function updated(Interview $i): void
    {
        // Si pasó a "cancelled" => eliminar de Google y borrar vínculo
        if ($i->isDirty('status') && $i->status === 'cancelled') {
            $this->syncDelete($i);

            return;
        }

        // Si pasó de 'confirmed' a 'pending' => eliminar de Google
        if ($i->isDirty('status') && $i->status === 'pending' && $i->google_event_id) {
            $this->syncDelete($i);

            return;
        }

        // Si cambió título/horarios/estado (no cancelado ni pending) => upsert
        if ($i->wasChanged(['title', 'start_at', 'end_at', 'status'])) {
            $this->syncUpsert($i);
        }
    }

    public function deleted(Interview $i): void
    {
        $this->syncDelete($i);
    }

    /** Crea/actualiza el evento en Google y guarda google_event_id sin recursión. */
    protected function syncUpsert(Interview $i): void
    {
        try {
            // Solo sincronizar si el estado es 'confirmed'
            if ($i->status !== 'confirmed') {
                return;
            }

            /** @var GoogleCalendarService $svc */
            $svc = app(GoogleCalendarService::class);
            $eventId = $svc->upsertInterviewEventForOwner($i);

            if ($i->google_event_id !== $eventId) {
                $i->withoutEvents(function () use ($i, $eventId) {
                    $i->forceFill(['google_event_id' => $eventId])->saveQuietly();
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Google sync upsert failed', [
                'interview_id' => $i->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Borra el evento en Google (si existe) y limpia el campo. */
    protected function syncDelete(Interview $i): void
    {
        try {
            if (! $i->google_event_id) {
                return;
            }

            /** @var GoogleCalendarService $svc */
            $svc = app(GoogleCalendarService::class);
            $svc->deleteInterviewEventForOwner($i);

            $i->withoutEvents(function () use ($i) {
                $i->forceFill(['google_event_id' => null])->saveQuietly();
            });
        } catch (\Throwable $e) {
            Log::warning('Google sync delete failed', [
                'interview_id' => $i->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
