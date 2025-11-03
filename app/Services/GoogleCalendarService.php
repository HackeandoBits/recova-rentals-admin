<?php

namespace App\Services;

use App\Models\GoogleToken;
use App\Models\Interview;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
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

        $client = new GoogleClient();
        $client->setClientId(Config::get('services.google.client_id'));
        $client->setClientSecret(Config::get('services.google.client_secret'));
        $client->setRedirectUri(Config::get('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            GoogleCalendar::CALENDAR_EVENTS,
            GoogleCalendar::CALENDAR_READONLY,
        ]);

        // Cargar token actual al cliente
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in'    => $token->expires_at
                ? max(0, now()->diffInSeconds($token->expires_at, false))
                : 3600,
        ]);

        // Refrescar si venció
        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            try {
                $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

                // Persistir nuevos datos si llegaron
                $token->update([
                    'access_token' => $new['access_token'] ?? $token->access_token,
                    'expires_at'   => isset($new['expires_in'])
                        ? now()->addSeconds((int) $new['expires_in'])
                        : $token->expires_at,
                ]);

                // Reinyectar al cliente con los datos actualizados
                $client->setAccessToken([
                    'access_token'  => $token->access_token,
                    'refresh_token' => $token->refresh_token,
                    'expires_in'    => $token->expires_at
                        ? max(0, now()->diffInSeconds($token->expires_at, false))
                        : 3600,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Google token refresh failed', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
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
        $startAt = $i->start_at instanceof \Carbon\Carbon ? $i->start_at : \Carbon\Carbon::parse($i->start_at);
        $endAt   = $i->end_at   instanceof \Carbon\Carbon ? $i->end_at   : \Carbon\Carbon::parse($i->end_at);

        $tz = config('app.timezone', 'UTC');

        $payload = new Event([
            'summary' => $i->title ?? 'Entrevista',
            'start'   => [
                'dateTime' => $startAt->toRfc3339String(),
                'timeZone' => $tz,
            ],
            'end'     => [
                'dateTime' => $endAt->toRfc3339String(),
                'timeZone' => $tz,
            ],
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
}
