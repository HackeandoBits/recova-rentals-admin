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
    /**
     * Iniciar flujo de LOGIN con Google (incluye Calendar automáticamente)
     */
    public function loginWithGoogle(Request $request): RedirectResponse
    {
        // Guardamos que es un flujo de LOGIN (incluye calendar)
        $request->session()->put('oauth_action', 'login');

        // Pedimos permisos de login + calendar
        $scopes = [
            'openid', 'email', 'profile',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.readonly',
        ];

        $extra = [
            'access_type' => 'offline', // Para obtener refresh_token de Calendar
            'include_granted_scopes' => 'true',
            'prompt' => 'consent', // Forzar consentimiento para refresh_token
        ];

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with($extra)
            ->redirect();
    }

    /**
     * Iniciar flujo de CONECTAR Google Calendar (requiere estar logueado)
     */
    public function redirect(Request $request): RedirectResponse
    {
        // Debe estar logueado en tu app antes de conectar su Google
        if (! Auth::check()) {
            return redirect()->to($this->filamentLoginUrl())
                ->with('error', 'Iniciá sesión para vincular tu Google Calendar.');
        }

        // Guardamos el user_id del usuario que va a vincular
        $request->session()->put('oauth_user_id', Auth::id());
        $request->session()->put('oauth_action', 'connect_calendar');

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
            'access_type' => 'offline',            // necesario para refresh_token
            'include_granted_scopes' => 'true',
            'prompt' => ($force || $needsRefreshToken) ? 'consent' : 'none',
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
            'oauth_action' => $request->session()->get('oauth_action'),
        ]);

        // Determinar qué acción se está realizando
        $action = $request->session()->pull('oauth_action', 'connect_calendar');

        // Obtener los datos del usuario de Google
        try {
            \Illuminate\Support\Facades\Log::info('GoogleAuth: Requesting user from Socialite...');

            // FIX: Deshabilitar verificación SSL para entorno local (Laragon)
            $driver = Socialite::driver('google');
            $driver->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

            $googleUser = $driver->user();
            \Illuminate\Support\Facades\Log::info('GoogleAuth: Socialite user received');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GoogleAuth: Socialite Error (Stateful): '.$e->getMessage());
            // En algunos navegadores/extensiones puede fallar el estado; probamos stateless
            try {
                \Illuminate\Support\Facades\Log::info('GoogleAuth: Retrying stateless...');

                $driver = Socialite::driver('google')->stateless();
                $driver->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

                $googleUser = $driver->user();
                \Illuminate\Support\Facades\Log::info('GoogleAuth: Socialite user received (Stateless)');
            } catch (\Throwable $e2) {
                \Illuminate\Support\Facades\Log::error('GoogleAuth: Socialite Error (Stateless): '.$e2->getMessage());

                return redirect()->to($this->filamentLoginUrl())
                    ->with('error', 'Error al obtener las credenciales de Google: '.$e2->getMessage());
            }
        }

        // Manejar según la acción
        if ($action === 'login') {
            return $this->handleLogin($googleUser);
        } else {
            return $this->handleConnectCalendar($googleUser, $request);
        }
    }

    /**
     * Manejar LOGIN: Buscar o crear usuario, autenticar Y guardar tokens de Calendar
     */
    protected function handleLogin($googleUser): RedirectResponse
    {
        // Buscar usuario por google_id o email
        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // Buscar por email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Usuario existe con ese email, vincular google_id
                $user->update(['google_id' => $googleUser->getId()]);
            } else {
                // NO PERMITIR REGISTRO AUTOMÁTICO
                return redirect()->to($this->filamentLoginUrl())
                    ->with('error', 'El email ' . $googleUser->getEmail() . ' no está registrado en el sistema. Contactá al administrador.');
            }
        }

        // Autenticar al usuario
        Auth::login($user, remember: true);
        \Illuminate\Support\Facades\Log::info('User logged in via Google', ['user_id' => $user->id]);

        // Guardar tokens de Google Calendar
        $access = $googleUser->token;
        $refresh = $googleUser->refreshToken ?? null;
        $expires = $googleUser->expiresIn ?? 3600;

        $record = GoogleToken::firstOrNew(['user_id' => $user->id]);

        $payload = [
            'access_token' => $access,
            'expires_at' => now('UTC')->addSeconds((int) $expires),
            'google_user_id' => $googleUser->getId(),
            'google_email' => $googleUser->getEmail(),
            'id_token' => $googleUser->id_token ?? null,
            'scopes' => json_encode([
                'openid', 'email', 'profile',
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/calendar.readonly',
            ], JSON_UNESCAPED_SLASHES),
            'revoked' => false,
        ];

        if (! empty($refresh)) {
            $payload['refresh_token'] = $refresh;
        }

        try {
            $record->fill($payload)->save();
            \Illuminate\Support\Facades\Log::info('GoogleToken saved for user', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error saving GoogleToken: '.$e->getMessage());
        }

        // Sincronizar pendientes (opcional)
        try {
            $pendingBlocks = \App\Models\CalendarBlock::where('sync_status', 'pending')
                ->where('owner_user_id', $user->id)
                ->get();

            foreach ($pendingBlocks as $block) {
                \App\Jobs\SyncSingleBlockJob::dispatchSync($block->id);
            }

            $count = $pendingBlocks->count();
            if ($count > 0) {
                \Illuminate\Support\Facades\Log::info("Synced {$count} pending calendar blocks");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error syncing pending items: '.$e->getMessage());
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', '¡Bienvenido, '.$user->name.'! Google Calendar conectado.');
    }

    /**
     * Manejar CONECTAR CALENDAR: Guardar tokens de Google
     */
    protected function handleConnectCalendar($googleUser, Request $request): RedirectResponse
    {
        // Recuperamos a quién asociar el token
        $userId = $request->session()->pull('oauth_user_id');

        if (! $userId) {
            \Illuminate\Support\Facades\Log::warning('GoogleAuth: No user ID in session');

            return redirect()->to($this->filamentLoginUrl())
                ->with('error', 'Volvé a iniciar sesión y reintentá conectar Google.');
        }

        // Tokens y metadatos
        $access = $googleUser->token;
        $refresh = $googleUser->refreshToken ?? null;
        $expires = $googleUser->expiresIn ?? 3600;

        \Illuminate\Support\Facades\Log::info('GoogleAuth Callback', [
            'user_id' => $userId,
            'has_access' => ! empty($access),
            'has_refresh' => ! empty($refresh),
            'expires' => $expires,
        ]);

        $record = GoogleToken::firstOrNew(['user_id' => $userId]);

        $payload = [
            'access_token' => $access,
            'expires_at' => now('UTC')->addSeconds((int) $expires),
            'google_user_id' => method_exists($googleUser, 'getId') ? $googleUser->getId() : null,
            'google_email' => method_exists($googleUser, 'getEmail') ? $googleUser->getEmail() : null,
            'id_token' => $googleUser->id_token ?? null,
            'scopes' => json_encode([
                'openid', 'email', 'profile',
                'https://www.googleapis.com/auth/calendar.events',
                'https://www.googleapis.com/auth/calendar.readonly',
            ], JSON_UNESCAPED_SLASHES),
            'revoked' => false,
        ];

        // No pisar un refresh_token válido con null
        if (! empty($refresh)) {
            $payload['refresh_token'] = $refresh;
        }

        try {
            $record->fill($payload)->save();
            \Illuminate\Support\Facades\Log::info('GoogleToken saved successfully', ['id' => $record->id]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error saving GoogleToken: '.$e->getMessage());

            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'Error al guardar el token: '.$e->getMessage());
        }

        // Aseguramos sesión del usuario (por si se perdió)
        if (! Auth::check()) {
            if ($user = User::find($userId)) {
                Auth::login($user, remember: true);
            }
        }

        // --- SINCRONIZACIÓN AUTOMÁTICA DE PENDIENTES ---
        try {
            $pendingBlocks = \App\Models\CalendarBlock::where('sync_status', 'pending')
                ->where('owner_user_id', $userId)
                ->get();

            foreach ($pendingBlocks as $block) {
                \App\Jobs\SyncSingleBlockJob::dispatchSync($block->id);
            }

            $pendingInterviews = \App\Models\Interview::where('status', 'confirmed')
                ->get();

            foreach ($pendingInterviews as $interview) {
                if (empty($interview->google_event_id)) {
                    if (class_exists(\App\Jobs\SyncInterviewJob::class)) {
                        \App\Jobs\SyncInterviewJob::dispatchSync($interview->id);
                    }
                }
            }

            $count = $pendingBlocks->count() + $pendingInterviews->count();
            $msg = "Google Calendar conectado. Se sincronizaron {$count} elementos pendientes.";
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sync on connect: '.$e->getMessage());
            $msg = 'Google Calendar conectado, pero hubo un error al sincronizar pendientes.';
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', $msg);
    }

    protected function filamentLoginUrl(): string
    {
        foreach (['filament.admin.auth.login', 'filament.auth.login', 'filament.panel.auth.login'] as $name) {
            if (RouteFacade::has($name)) {
                return route($name);
            }
        }

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
            $token->delete();
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', 'Google Calendar desconectado correctamente.');
    }
}
