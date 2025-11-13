<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea/actualiza al dueño como único admin que necesitamos para las pruebas.
     * Email: recovarentals@gmail.com
     * Password: Peluk@2025
     */
    public function run(): void
    {
        // Elimina cualquier admin ficticio anterior si existiera
        User::where('email', 'admin@recova.com')->delete();

        // Upsert del dueño
        User::updateOrCreate(
            ['email' => 'recovarentals@gmail.com'],
            [
                'name' => 'Recova Rentals Owner',
                'is_admin' => true,           // asegura rol admin
                'email_verified_at' => now(),          // verificado
                'password' => Hash::make('recova123'),
            ]
        );
    }
}
