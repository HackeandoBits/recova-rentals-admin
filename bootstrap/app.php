<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            // Si la petición espera JSON, dejar que Laravel lo maneje
            if ($request->expectsJson()) {
                return null;
            }

            // Si es una petición de Filament (starts with /admin normally)
            if ($request->is('admin*')) {
                \Filament\Notifications\Notification::make()
                    ->title('Acceso Denegado')
                    ->body('No tienes permisos para realizar esta acción.')
                    ->danger()
                    ->send();

                return redirect()->back(); // O redirect()->to('/admin');
            }

            return null; // Fallback to default
        });
    })->create();
