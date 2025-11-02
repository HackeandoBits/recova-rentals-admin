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

        // Si ya tiene token, evitamos re-pedir consentimiento (prompt=none)
        $hasToken = GoogleToken::where('user_id', Auth::id())->exists();

        $scopes = [
            'openid', 'email', 'profile',
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.readonly',
        ];

        $extra = [
            'access_type' => 'offline',
            'prompt'      => $hasToken ? 'none' : 'consent',
            // 'include_granted_scopes' => 'true', // opcional
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

        // Guardar/actualizar tokens
        GoogleToken::updateOrCreate(
            ['user_id' => $userId],
            [
                'access_token'  => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken ?? null, // sólo llega la 1ª vez con consent
                'expires_at'    => $googleUser->expiresIn
                    ? now()->addSeconds($googleUser->expiresIn)
                    : null,
            ]
        );

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
