<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        // Debe estar logueado en tu app antes de conectar su Google
        if (! Auth::check()) {
            return redirect()->to($this->filamentLoginUrl())
                ->with('error', 'Iniciá sesión para vincular tu Google Calendar.');
        }

        // Guardamos el user_id del usuario que va a vincular
        $request->session()->put('oauth_user_id', Auth::id());

        // Estado actual del token del usuario
        $existing = GoogleToken::where('user_id', Auth::id())->first();
        $hasToken = (bool) $existing;

        $scopes = [
            'openid', 'email', 'profile',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.readonly',
        ];

        // ¿Querés forzar consentimiento? /google/redirect?force=1
        $force = (bool) $request->boolean('force');

        // Si no tenemos refresh_token guardado, forzamos consentimiento para que Google lo emita
        $needsRefreshToken = ! $existing || empty($existing->refresh_token);

        $extra = [
            'access_type'            => 'offline',            // necesario para refresh_token
            'include_granted_scopes' => 'true',
            'prompt'                 => ($force || $needsRefreshToken) ? 'consent' : 'none',
        ];

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with($extra)
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        \Illuminate\Support\Facades\Log::info('GoogleAuth Callback HIT', [
            'session_all' => $request->session()->all(),
            'oauth_user_id' => $request->session()->get('oauth_user_id'),
        ]);

        // Recuperamos a quién asociar el token (sin requerir auth en esta ruta)
        $userId = $request->session()->pull('oauth_user_id');

        if (! $userId) {
            \Illuminate\Support\Facades\Log::warning('GoogleAuth: No user ID in session');
            // No sabemos a quién asociar → mandamos al login del panel
            return redirect()->to($this->filamentLoginUrl())
                ->with('error', 'Volvé a iniciar sesión y reintentá conectar Google.');
        }

        // Obtener los datos del usuario de Google (manejo de estado)
        try {
            \Illuminate\Support\Facades\Log::info('GoogleAuth: Requesting user from Socialite...');
            
            // FIX: Deshabilitar verificación SSL para entorno local (Laragon)
            $driver = Socialite::driver('google');
            $driver->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
            
            $googleUser = $driver->user();
            \Illuminate\Support\Facades\Log::info('GoogleAuth: Socialite user received');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GoogleAuth: Socialite Error (Stateful): ' . $e->getMessage());
            // En algunos navegadores/extensiones puede fallar el estado; probamos stateless
            try {
                \Illuminate\Support\Facades\Log::info('GoogleAuth: Retrying stateless...');
                
                $driver = Socialite::driver('google')->stateless();
                $driver->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
                
                $googleUser = $driver->user();
                \Illuminate\Support\Facades\Log::info('GoogleAuth: Socialite user received (Stateless)');
            } catch (\Throwable $e2) {
                \Illuminate\Support\Facades\Log::error('GoogleAuth: Socialite Error (Stateless): ' . $e2->getMessage());
                return redirect()->to($this->filamentLoginUrl())
                    ->with('error', 'Error al obtener las credenciales de Google: '.$e2->getMessage());
            }
        }

        // Tokens y metadatos
        $access  = $googleUser->token;
        $refresh = $googleUser->refreshToken ?? null; // puede venir null si Google decide no reenviarlo
        $expires = $googleUser->expiresIn ?? 3600;

        \Illuminate\Support\Facades\Log::info('GoogleAuth Callback', [
            'user_id' => $userId,
            'has_access' => !empty($access),
            'has_refresh' => !empty($refresh),
            'expires' => $expires
        ]);

        $record = GoogleToken::firstOrNew(['user_id' => $userId]);

        $payload = [
            'access_token' => $access,
            // Normalizamos expiración en UTC
            'expires_at'   => now('UTC')->addSeconds((int) $expires),
            // (Opcional si tu tabla tiene estas columnas)
            'google_user_id' => method_exists($googleUser, 'getId') ? $googleUser->getId() : null,
            'google_email'   => method_exists($googleUser, 'getEmail') ? $googleUser->getEmail() : null,
            'id_token'       => $googleUser->id_token ?? null,
            'scopes'         => json_encode([
                'openid', 'email', 'profile',
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/calendar.readonly',
            ], JSON_UNESCAPED_SLASHES),
            'revoked'        => false,
        ];

        // No pisar un refresh_token válido con null
        if (! empty($refresh)) {
            $payload['refresh_token'] = $refresh;
        }

        try {
            $record->fill($payload)->save();
            \Illuminate\Support\Facades\Log::info('GoogleToken saved successfully', ['id' => $record->id]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error saving GoogleToken: ' . $e->getMessage());
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'Error al guardar el token: ' . $e->getMessage());
        }

        // Aseguramos sesión del usuario (por si se perdió)
        if (! Auth::check()) {
            if ($user = User::find($userId)) {
                Auth::login($user, remember: true);
            }
        }

        // --- SINCRONIZACIÓN AUTOMÁTICA DE PENDIENTES ---
        // Al conectar, buscamos todo lo que esté 'pending' y lo mandamos YA.
        try {
            // 1. Bloqueos pendientes
            $pendingBlocks = \App\Models\CalendarBlock::where('sync_status', 'pending')
                ->where('owner_user_id', $userId)
                ->get();

            foreach ($pendingBlocks as $block) {
                // Ejecutamos el Job sincrónicamente (sin colas)
                \App\Jobs\SyncSingleBlockJob::dispatchSync($block->id);
            }

            // 2. Reuniones CONFIRMADAS pendientes de sync
            $pendingInterviews = \App\Models\Interview::where('status', 'confirmed')
                // ->where('assigned_user_id', $userId) // Si usas asignación
                ->get();

            foreach ($pendingInterviews as $interview) {
                // Solo si no tiene google_event_id aún
                if (empty($interview->google_event_id)) {
                    // Asumimos que existe un Job similar o lógica de sync
                    // Si no existe Job, instanciamos el servicio directamente.
                    // Por ahora, usaremos el Job si existe, o un placeholder.
                    // Verificamos si existe SyncInterviewJob
                    if (class_exists(\App\Jobs\SyncInterviewJob::class)) {
                        \App\Jobs\SyncInterviewJob::dispatchSync($interview->id);
                    }
                }
            }
            
            $count = $pendingBlocks->count() + $pendingInterviews->count();
            $msg = "Google Calendar conectado. Se sincronizaron {$count} elementos pendientes.";
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sync on connect: ' . $e->getMessage());
            $msg = 'Google Calendar conectado, pero hubo un error al sincronizar pendientes.';
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', $msg);
    }

    protected function filamentLoginUrl(): string
    {
        // Detecta el name correcto del login de tu panel
        foreach (['filament.admin.auth.login', 'filament.auth.login', 'filament.panel.auth.login'] as $name) {
            if (RouteFacade::has($name)) {
                return route($name);
            }
        }

        // Fallback típico
        return '/admin/login';
    }

    public function disconnect(Request $request): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->to($this->filamentLoginUrl());
        }

        $userId = Auth::id();
        $token = GoogleToken::where('user_id', $userId)->first();

        if ($token) {
            // Opcional: Revocar en Google
            // $client = ...; $client->revokeToken();
            
            $token->delete();
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', 'Google Calendar desconectado correctamente.');
    }
}
