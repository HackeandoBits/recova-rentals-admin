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
     * Verifica si el usuario (o el owner por defecto) tiene un token de Google.
     */
    public function isConnected(?int $userId = null): bool
    {
        // Si no se pasa ID, intentamos usar el del usuario logueado
        if ($userId === null && auth()->check()) {
            $userId = auth()->id();
        }

        // Si sigue nulo (no logueado y no pasado), fallback al owner config
        $userId ??= (int) config('owner.calendar_user_id', 1);

        return GoogleToken::where('user_id', $userId)->exists();
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
     * Obtiene un evento específico del calendario del owner.
     */
    public function getEvent(string $eventId, string $calendarId = 'primary'): ?GoogleEvent
    {
        try {
            return $this->forOwner()->events->get($calendarId, $eventId);
        } catch (\Throwable $e) {
            // Si es 404 o error, retornamos null
            return null;
        }
    }

    /**
     * Sincroniza eventos desde Google hacia la BD (Importar).
     * Los eventos externos (no encontrados localmente) se crearán como CalendarBlock.
     */
    public function syncFromGoogle(Carbon $start, Carbon $end): array
    {
        $importedCount = 0;
        $errors = [];
        $ownerId = (int) config('owner.calendar_user_id', 1);

        // 1. Obtener eventos manuales del calendario principal
        $nativeEvents = $this->listEvents($start, $end, 'primary');
        Log::info('SyncFromGoogle: Native Events Count: '.count($nativeEvents));

        // 2. Obtener feriados
        $holidayEvents = $this->listEvents($start, $end, 'es.ar#holiday@group.v.calendar.google.com');
        Log::info('SyncFromGoogle: Holiday Events Count: '.count($holidayEvents));

        // IDs nativos ya conocidos para evitar duplicados locales
        $knownGoogleIds = Interview::whereNotNull('google_event_id')
            ->pluck('google_event_id')
            ->concat(CalendarBlock::whereNotNull('google_event_id')->pluck('google_event_id'))
            ->flip();

        Log::info('SyncFromGoogle: Known Google IDs Count: '.$knownGoogleIds->count());

        // A) Procesar Feriados -> SIEMPRE CalendarBlock
        foreach ($holidayEvents as $gEvent) {
            $gId = $gEvent->getId();
            if ($knownGoogleIds->has($gId)) {
                continue;
            }

            if ($err = $this->createBlockFromGoogle($gEvent, 'feriado', $ownerId)) {
                $errors[] = "Feriado ({$gEvent->getSummary()}): $err";
            }
            $importedCount++;
        }

        // B) Procesar Eventos Nativos -> CalendarBlock (si es AllDay) o Interview (si tiene hora)
        foreach ($nativeEvents as $gEvent) {
            $gId = $gEvent->getId();
            if ($knownGoogleIds->has($gId)) {
                continue;
            }

            $isAllDay = empty($gEvent->start->dateTime);

            if ($isAllDay) {
                // Eventos de todo el día -> CalendarBlock
                if ($err = $this->createBlockFromGoogle($gEvent, 'otro', $ownerId)) {
                    $errors[] = "Bloqueo ({$gEvent->getSummary()}): $err";
                }
            } else {
                // Eventos con hora -> Interview (Reunión)
                if ($err = $this->createInterviewFromGoogle($gEvent)) {
                    $errors[] = "Reunión ({$gEvent->getSummary()}): $err";
                }
            }
            $importedCount++;
        }

        Log::info("SyncFromGoogle: Total Imported: $importedCount");

        return [
            'count' => $importedCount,
            'errors' => $errors,
        ];
    }

    private function createBlockFromGoogle(GoogleEvent $gEvent, string $kind, int $ownerId): ?string
    {
        $isAllDay = empty($gEvent->start->dateTime);

        if ($isAllDay) {
            $s = Carbon::parse($gEvent->start->date)->startOfDay();
            $e = Carbon::parse($gEvent->end->date)->startOfDay();

            // Fix: Si Google devuelve start == end (duración 0), forzamos 1 día
            if ($e->lte($s)) {
                $e = $s->copy()->addDay();
            }
        } else {
            $s = Carbon::parse($gEvent->start->dateTime);
            $e = Carbon::parse($gEvent->end->dateTime);

            // Fix: Si duración es 0 o negativa, forzamos 1 hora (60 min)
            if ($e->lte($s)) {
                $e = $s->copy()->addHour();
            }
        }

        try {
            CalendarBlock::create([
                'title' => $gEvent->getSummary() ?: ($kind === 'feriado' ? 'Feriado' : 'Evento Google'),
                'starts_at' => $s,
                'ends_at' => $e,
                'is_all_day' => $isAllDay,
                'kind' => $kind,
                'reason' => $gEvent->getDescription(),
                'owner_user_id' => $ownerId,
                'google_event_id' => $gEvent->getId(),
                'sync_status' => 'synced',
                'synced_at' => now(),
            ]);

            return null; // Success
        } catch (\Exception $e) {
            Log::error("SyncFromGoogle: Failed to create block for event {$gEvent->getId()}. Error: ".$e->getMessage());

            return $e->getMessage();
        }
    }

    private function createInterviewFromGoogle(GoogleEvent $gEvent): ?string
    {
        try {
            $s = Carbon::parse($gEvent->start->dateTime);
            $e = Carbon::parse($gEvent->end->dateTime);

            // Fix: Asegurar duración mínima de 1 hora
            if ($e->lte($s)) {
                $e = $s->copy()->addHour();
            }

            Interview::create([
                'title' => $gEvent->getSummary() ?: 'Reunión Google',
                'start_at' => $s,
                'end_at' => $e,
                'status' => 'confirmed',
                'channel' => 'google_calendar',
                'google_event_id' => $gEvent->getId(),
                'customer_name' => 'Google Calendar',
                'customer_email' => null,
                'customer_phone' => null,
                'order_notes' => $gEvent->getDescription(),
            ]);

            return null; // Success
        } catch (\Exception $e) {
            Log::error("SyncFromGoogle: Failed to create interview for event {$gEvent->getId()}. Error: ".$e->getMessage());

            return $e->getMessage();
        }
    }
}
