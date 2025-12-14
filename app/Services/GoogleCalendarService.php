<?php

namespace App\Services;

use App\Models\CalendarBlock;
use App\Models\GoogleToken;
use App\Models\Interview;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    /**
     * TZ nombrada del calendario del dueño (maneja DST). Fallback a Buenos Aires.
     * Definila en config/services.php => ['google_calendar' => ['timezone' => env('OWNER_CAL_TZ', 'America/Argentina/Buenos_Aires')]]
     */
    protected function calendarTz(): string
    {
        return config('services.google_calendar.timezone')
            ?? env('OWNER_CAL_TZ', 'America/Argentina/Buenos_Aires');
    }

    /**
     * Devuelve el cliente de Calendar listo para usar para un user_id dado.
     */
    public function forUser(int $userId): GoogleCalendar
    {
        $client = $this->clientWithFreshToken($userId);

        return new GoogleCalendar($client);
    }

    /**
     * Devuelve el cliente usando el OWNER_CAL_USER_ID (dueño).
     */
    public function forOwner(): GoogleCalendar
    {
        $ownerId = (int) config('owner.calendar_user_id', 1);

        return $this->forUser($ownerId);
    }

    /**
     * Crea un GoogleClient con token actual/refrescado y lo retorna.
     */
    protected function clientWithFreshToken(int $userId): GoogleClient
    {
        $token = GoogleToken::where('user_id', $userId)->firstOrFail();

        $client = new GoogleClient;
        $client->setClientId(Config::get('services.google.client_id'));
        $client->setClientSecret(Config::get('services.google.client_secret'));
        $client->setRedirectUri(Config::get('services.google.redirect'));
        $client->setAccessType('offline'); // el refresh_token viene del flujo OAuth
        // No forzamos 'consent' aquí; eso va en el controlador de OAuth.
        $client->setScopes([
            GoogleCalendar::CALENDAR_EVENTS,
            GoogleCalendar::CALENDAR_READONLY,
        ]);

        // FIX: Deshabilitar verificación SSL para entorno local (Laragon)
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

        // Cargar token actual al cliente
        $client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at
                ? max(0, now('UTC')->diffInSeconds($token->expires_at, false))
                : 3600,
        ]);

        // Refrescar si venció
        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            try {
                $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

                // Persistir nuevos datos si llegaron
                $token->update([
                    'access_token' => $new['access_token'] ?? $token->access_token,
                    'expires_at' => isset($new['expires_in'])
                        ? now('UTC')->addSeconds((int) $new['expires_in'])
                        : $token->expires_at,
                    'revoked' => false,
                ]);

                // Reinyectar al cliente con los datos actualizados
                $client->setAccessToken([
                    'access_token' => $token->access_token,
                    'refresh_token' => $token->refresh_token,
                    'expires_in' => $token->expires_at
                        ? max(0, now('UTC')->diffInSeconds($token->expires_at, false))
                        : 3600,
                ]);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $isInvalidGrant = str_contains($msg, 'invalid_grant');
                Log::warning('Google token refresh failed', [
                    'user_id' => $userId,
                    'error' => $msg,
                    'invalid_grant' => $isInvalidGrant,
                ]);
                if ($isInvalidGrant) {
                    // Marcamos revocado para forzar reautenticación en UI/CLI
                    $token->update(['revoked' => true]);
                }
                throw $e;
            }
        }

        return $client;
    }

    /**
     * Crea o actualiza el evento de una entrevista para un usuario dado.
     * Retorna el ID del evento de Google.
     */
    public function upsertInterviewEvent(
        int $userId,
        Interview $i,
        string $calendarId = 'primary'
    ): string {
        $cal = $this->forUser($userId);

        // Asegurar tipos datetime (por si vienen como string)
        $startAt = $i->start_at instanceof Carbon ? $i->start_at : Carbon::parse($i->start_at);
        $endAt = $i->end_at   instanceof Carbon ? $i->end_at : Carbon::parse($i->end_at);

        $tz = $this->calendarTz();
        $isAllDay = (bool) data_get($i, 'all_day', false);

        if ($isAllDay) {
            // All-day: usar 'date' y end exclusivo (sin timeZone)
            $startDate = $startAt->timezone($tz)->toDateString();
            $endDate = $endAt->timezone($tz)->toDateString();
            if ($endDate === $startDate) {
                $endDate = $startAt->timezone($tz)->addDay()->toDateString();
            }
            $start = ['date' => $startDate];
            $end = ['date' => $endDate];
        } else {
            // Con hora: dateTime SIN 'Z' + timeZone con nombre IANA
            $start = [
                'dateTime' => $startAt->timezone($tz)->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ];
            $end = [
                'dateTime' => $endAt->timezone($tz)->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ];
        }

        $payload = new GoogleEvent([
            'summary' => $i->title ?? 'Entrevista',
            'description' => trim(($i->description ?? '')."\nID: {$i->id}"),
            'start' => $start,
            'end' => $end,
        ]);

        if ($i->google_event_id) {
            // update
            $updated = $cal->events->update($calendarId, $i->google_event_id, $payload);

            return $updated->getId();
        }

        // create
        $created = $cal->events->insert($calendarId, $payload);

        return $created->getId();
    }

    /**
     * Borra el evento de una entrevista para un usuario dado (si existe).
     */
    public function deleteInterviewEvent(
        int $userId,
        Interview $i,
        string $calendarId = 'primary'
    ): void {
        if (! $i->google_event_id) {
            return;
        }

        $cal = $this->forUser($userId);
        $cal->events->delete($calendarId, $i->google_event_id);
    }

    /**
     * Atajo: upsert usando el OWNER_CAL_USER_ID.
     */
    public function upsertInterviewEventForOwner(
        Interview $i,
        string $calendarId = 'primary'
    ): string {
        $ownerId = (int) config('owner.calendar_user_id', 1);

        return $this->upsertInterviewEvent($ownerId, $i, $calendarId);
    }

    /**
     * Atajo: delete usando el OWNER_CAL_USER_ID.
     */
    public function deleteInterviewEventForOwner(
        Interview $i,
        string $calendarId = 'primary'
    ): void {
        $ownerId = (int) config('owner.calendar_user_id', 1);
        $this->deleteInterviewEvent($ownerId, $i, $calendarId);
    }

    /** Crea/actualiza evento en el calendario del OWNER para representar un CalendarBlock. */
    public function upsertBlock(CalendarBlock $block): ?GoogleEvent
    {
        $service = $this->forOwner();
        $calendarId = 'primary';
        $tz = $this->calendarTz();

        if ($block->is_all_day) {
            $startDate = $block->starts_at->timezone($tz)->toDateString();
            $endDate = $block->ends_at->timezone($tz)->toDateString();
            if ($endDate === $startDate) {
                $endDate = $block->starts_at->timezone($tz)->addDay()->toDateString();
            }
            $start = ['date' => $startDate]; // sin timeZone
            $end = ['date' => $endDate];   // sin timeZone
        } else {
            $start = [
                'dateTime' => $block->starts_at->timezone($tz)->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ];
            $end = [
                'dateTime' => $block->ends_at->timezone($tz)->format('Y-m-d\TH:i:s'),
                'timeZone' => $tz,
            ];
        }

        $event = new GoogleEvent([
            'summary' => '[BLOCK] '.($block->title ?: 'Bloqueo'),
            'description' => trim(($block->reason ?: '')."\nKind: {$block->kind}"),
            'start' => $start,
            'end' => $end,
            'colorId' => '11', // rojo
        ]);

        try {
            if ($block->google_event_id) {
                $event = $service->events->update($calendarId, $block->google_event_id, $event);
            } else {
                $event = $service->events->insert($calendarId, $event);
            }
            $block->update([
                'google_event_id' => $event->id,
                'sync_status' => 'synced',
                'synced_at' => now(),
                'last_error' => null,
            ]);

            return $event;
        } catch (\Throwable $e) {
            $block->update([
                'sync_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            report($e);

            return null;
        }
    }

    public function deleteBlockEvent(CalendarBlock $block): void
    {
        if (! $block->google_event_id) {
            return;
        }
        try {
            $this->forOwner()->events->delete('primary', $block->google_event_id);
        } catch (\Throwable $e) {
            report($e); // si ya no existe en google, ignorar
        }
        $block->update([
            'google_event_id' => null,
            'sync_status' => 'pending',
            'synced_at' => null,
        ]);
    }

    /**
     * List events from the owner's calendar within a given date range.
     *
     * @return \Google\Service\Calendar\Event[]
     */
    public function listEvents(Carbon $start, Carbon $end, string $calendarId = 'primary'): array
    {
        try {
            $service = $this->forOwner();
            $events = [];
            $pageToken = null;

            do {
                $optParams = [
                    'orderBy' => 'startTime',
                    'singleEvents' => true,
                    'timeMin' => $start->toRfc3339String(),
                    'timeMax' => $end->toRfc3339String(),
                    'pageToken' => $pageToken,
                    'maxResults' => 250, // Reasonable batch size
                ];

                $results = $service->events->listEvents($calendarId, $optParams);
                $items = $results->getItems();

                if (is_array($items)) {
                    $events = array_merge($events, $items);
                }

                $pageToken = $results->getNextPageToken();
            } while ($pageToken);

            return $events;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Google Calendar fetch skipped: Owner (User ID '.config('owner.calendar_user_id').') has not connected Google Calendar.');

            return [];
        } catch (\Throwable $e) {
            Log::error('Failed to list Google Calendar events: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Sincroniza eventos desde Google hacia la BD (Importar).
     * Los eventos externos (no encontrados localmente) se crearán como CalendarBlock.
     */
    public function syncFromGoogle(Carbon $start, Carbon $end): int
    {
        $importedCount = 0;
        $nativeEvents = $this->listEvents($start, $end, 'primary'); // Solo calendario principal

        // IDs nativos ya conocidos para evitar duplicados
        $knownGoogleIds = Interview::whereNotNull('google_event_id')
            ->pluck('google_event_id')
            ->concat(CalendarBlock::whereNotNull('google_event_id')->pluck('google_event_id'))
            ->flip(); // HashMap para búsqueda rápida

        foreach ($nativeEvents as $gEvent) {
            $gId = $gEvent->getId();

            // Si ya lo tenemos vinculado, ignorar
            if ($knownGoogleIds->has($gId)) {
                continue;
            }

            // Si es un evento "externo" (creado en Google), importarlo como Bloqueo
            $isAllDay = empty($gEvent->start->dateTime);

            // Parsear fechas
            if ($isAllDay) {
                // Fechas puras "Y-m-d"
                $s = Carbon::parse($gEvent->start->date);
                // Google "end" es exclusivo para allDay, pero nosotros guardamos bloqueos inclusivos o exclusivos?
                // Revisando CalendarBlock, parece usar starts_at/ends_at puros.
                // Ajuste: si es allDay, Google devuelve ej: start=2023-01-01, end=2023-01-02 para 1 día.
                // CalendarBlock suele requerir definir "is_all_day"
                $e = Carbon::parse($gEvent->end->date);
            } else {
                $s = Carbon::parse($gEvent->start->dateTime);
                $e = Carbon::parse($gEvent->end->dateTime);
            }

            // Crear Bloqueo
            CalendarBlock::create([
                'title' => $gEvent->getSummary() ?: 'Evento Google sin título',
                'starts_at' => $s,
                'ends_at' => $e,
                'is_all_day' => $isAllDay,
                'kind' => 'otro', // Marcar como externo/otro
                'reason' => $gEvent->getDescription(),
                'owner_user_id' => config('owner.calendar_user_id', 1),
                'google_event_id' => $gId,
                'sync_status' => 'synced',
                'synced_at' => now(),
            ]);

            $importedCount++;
        }

        return $importedCount;
    }
}
