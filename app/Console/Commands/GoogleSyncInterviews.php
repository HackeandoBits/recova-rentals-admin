<?php

namespace App\Console\Commands;

use App\Models\Interview;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class GoogleSyncInterviews extends Command
{
    protected $signature = 'google:sync-interviews
        {--owner : usar siempre el OWNER_CAL_USER_ID}
        {--userId= : forzar user_id específico}
        {--since= : ISO date/timeMin para filtrar (ej: 2025-10-01)}
        {--cancelled=keep : keep|clear - qué hacer con canceladas (keep = no tocar en Google; clear = borrar evento)}';

    protected $description = 'Backfill/Resync de entrevistas con Google Calendar';

    public function handle(GoogleCalendarService $svc): int
    {
        $owner = (bool) $this->option('owner');
        $userId = $this->option('userId') ? (int) $this->option('userId') : null;
        if ($owner) {
            $userId = (int) config('owner.calendar_user_id', 1);
        }
        if (! $userId) {
            $this->error('Debe indicar --owner o --userId=');

            return self::FAILURE;
        }

        $q = Interview::query()->orderBy('start_at');
        if ($since = $this->option('since')) {
            $q->where('start_at', '>=', $since);
        }

        $count = 0;
        $clearCancelled = $this->option('cancelled') === 'clear';

        $this->info("Sincronizando entrevistas para user_id={$userId}...");
        $this->withProgressBar($q->cursor(), function (Interview $i) use ($svc, $userId, $clearCancelled, &$count) {
            try {
                if ($i->status === 'cancelled') {
                    if ($clearCancelled && $i->google_event_id) {
                        $svc->deleteInterviewEvent($userId, $i);
                        $i->forceFill(['google_event_id' => null])->saveQuietly();
                    }

                    return;
                }

                $eid = $svc->upsertInterviewEvent($userId, $i);
                if ($i->google_event_id !== $eid) {
                    $i->forceFill(['google_event_id' => $eid])->saveQuietly();
                }
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Error interview #{$i->id}: ".$e->getMessage());
            }
        });

        $this->newLine();
        $this->info("Listo. Entrevistas sincronizadas: {$count}");

        return self::SUCCESS;
    }
}
