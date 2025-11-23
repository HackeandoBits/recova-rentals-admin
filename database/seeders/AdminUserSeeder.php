<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea/actualiza al dueño como único admin que necesitamos para las pruebas.
     * Genera el token de API para el Cliente Web.
     * Email: recovarentals@gmail.com
     * Password: recova123
     */
    public function run(): void
    {
        // Elimina cualquier admin ficticio anterior si existiera
        User::where('email', 'admin@recova.com')->delete();
        // 1. Upsert del dueño
        $user = User::updateOrCreate(
            ['email' => 'recovarentals@gmail.com'],
            [
                'name' => 'Recova Rentals Owner',
                'is_admin' => true,           // asegura rol admin
                'email_verified_at' => now(), // verificado
                'password' => Hash::make('recova123'),
            ]
        );

        // 2. Crear el Token para el Cliente Web (Si no existe)
        // Borramos tokens anteriores con el mismo nombre para evitar duplicados basura
        $user->tokens()->where('name', 'cliente-web')->delete();

        // Creamos el nuevo token
        $token = $user->createToken('cliente-web')->plainTextToken;

        // 3. IMPRIMIRLO EN LA CONSOLA (¡Esto es lo útil!)
        $this->command->info('------------------------------------------------');
        $this->command->info('✅ Usuario Admin (Dueño) Creado/Actualizado.');
        $this->command->info('🔑 TOKEN DE API GENERADO (Copiá esto en el .env del Cliente):');
        $this->command->warn($token);
        $this->command->info('------------------------------------------------');
    }
}
