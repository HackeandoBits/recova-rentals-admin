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
        // Recuperamos a quién asociar el token (sin requerir auth en esta ruta)
        $userId = $request->session()->pull('oauth_user_id');

        if (! $userId) {
            // No sabemos a quién asociar → mandamos al login del panel
            return redirect()->to($this->filamentLoginUrl())
                ->with('error', 'Volvé a iniciar sesión y reintentá conectar Google.');
        }

        // Obtener los datos del usuario de Google (manejo de estado)
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            // En algunos navegadores/extensiones puede fallar el estado; probamos stateless
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
            } catch (\Throwable $e2) {
                return redirect()->to($this->filamentLoginUrl())
                    ->with('error', 'Error al obtener las credenciales de Google: '.$e2->getMessage());
            }
        }

        // Tokens y metadatos
        $access  = $googleUser->token;
        $refresh = $googleUser->refreshToken ?? null; // puede venir null si Google decide no reenviarlo
        $expires = $googleUser->expiresIn ?? 3600;

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

        $record->fill($payload)->save();

        // Aseguramos sesión del usuario (por si se perdió)
        if (! Auth::check()) {
            if ($user = User::find($userId)) {
                Auth::login($user, remember: true);
            }
        }

        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', 'Google Calendar conectado.');
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
}
