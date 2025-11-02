<?php

namespace App\Services;

use App\Models\GoogleToken;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Interview;
use Google\Service\Calendar\Event;

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

    public function forOwner(): GoogleCalendar
    {
        $ownerId = (int) config('owner.calendar_user_id');
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

        // Carga token actual
        $client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at
                ? max(0, now()->diffInSeconds($token->expires_at, false))
                : 3600,
        ]);

        // Refresca si vencido
        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            try {
                $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

                // Persistir nuevos datos si llegaron
                $token->update([
                    'access_token' => $new['access_token'] ?? $token->access_token,
                    'expires_at' => isset($new['expires_in'])
                        ? now()->addSeconds((int) $new['expires_in'])
                        : $token->expires_at,
                ]);

                // Reinyectar al cliente
                $client->setAccessToken([
                    'access_token' => $token->access_token,
                    'refresh_token' => $token->refresh_token,
                    'expires_in' => $token->expires_at
                        ? max(0, now()->diffInSeconds($token->expires_at, false))
                        : 3600,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Google token refresh failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $client;
    }

    public function upsertInterviewEvent(int $userId, Interview $i, string $calendarId = 'primary'): string
    {
        $cal = $this->forUser($userId);

        $payload = new Event([
            'summary' => $i->title ?? 'Entrevista',
            'start' => ['dateTime' => $i->start_at->toRfc3339String()],
            'end' => ['dateTime' => $i->end_at->toRfc3339String()],
        ]);

        if ($i->google_event_id) {
            // update
            $updated = $cal->events->update($calendarId, $i->google_event_id, $payload);
            return $updated->getId();
        } else {
            // create
            $created = $cal->events->insert($calendarId, $payload);
            return $created->getId();
        }
    }

    public function deleteInterviewEvent(int $userId, Interview $i, string $calendarId = 'primary'): void
    {
        if (!$i->google_event_id)
            return;

        $cal = $this->forUser($userId);
        $cal->events->delete($calendarId, $i->google_event_id);
    }
}
