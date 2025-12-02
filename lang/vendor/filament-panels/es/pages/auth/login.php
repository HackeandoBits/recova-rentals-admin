<?php

return [

    // Título del documento / pestaña
    'title' => 'Iniciar Sesión',

    // Título grande sobre el formulario
    'heading' => 'Iniciar Sesión',

    // Texto descriptivo bajo el título
    'subheading' => 'Ingresa tus credenciales para acceder al panel de administración.',

    'actions' => [

        'register' => [
            'before' => 'o',
            'label' => 'Abrir una cuenta',
        ],

        'request_password_reset' => [
            'label' => '¿Olvidaste tu contraseña?',
        ],

    ],

    'form' => [

        'email' => [
            'label' => 'Correo Electrónico',
            'placeholder' => 'admin@recova.com',
        ],

        'password' => [
            'label' => 'Contraseña',
            'placeholder' => 'Ingresa tu contraseña',
        ],

        'remember' => [
            'label' => 'Recordarme',
        ],

        'actions' => [

            // En algunas versiones la clave es "authenticate"
            'authenticate' => [
                'label' => 'Iniciar Sesión',
            ],

            // En otras es "login". Dejamos ambas por si acaso.
            'login' => [
                'label' => 'Iniciar Sesión',
            ],

        ],

    ],

    'messages' => [
        'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    ],

    'notifications' => [

        'throttled' => [
            'title' => 'Demasiados intentos.',
            'body' => 'Intenta de nuevo en :seconds segundos.',
        ],

    ],

];
