<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@recova.com'],
            [
                'name'      => 'Admin Recova',
                'password'  => Hash::make('recova123'),
                'is_admin'  => true,        // asegurate de tener esta columna
                'email_verified_at' => now()
            ]
        );
    }
}
