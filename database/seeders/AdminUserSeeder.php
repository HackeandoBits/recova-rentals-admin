<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Lista blanca de admins.
     */
    private array $adminEmails = [
        'leandronacimento04@gmail.com',
        'jajoultrapunk@gmail.com',
        'ginesparker95@gmail.com',
        'recovarentals@gmail.com',
    ];

    public function run(): void
    {
        // 1) Eliminar el admin ficticio
        User::where('email', 'admin@recova.com')->delete();

        // 2) (Opcional) Degradar a no-admin a cualquier otro que no esté en la lista
        //    Controlado por ENV para que sea seguro en prod.
        if (filter_var(env('ADMIN_EXCLUSIVE', true), FILTER_VALIDATE_BOOL)) {
            User::where('is_admin', true)
                ->whereNotIn('email', $this->adminEmails)
                ->update(['is_admin' => false]);
        }

        // 3) Crear/actualizar los 4 admins
        $defaultPassword = env('ADMIN_DEFAULT_PASSWORD', 'Recova#2025');
        $forceReset = filter_var(env('ADMIN_RESET_PASSWORDS', false), FILTER_VALIDATE_BOOL);

        foreach ($this->adminEmails as $email) {
            $name = Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->title();
            $user = User::firstWhere('email', $email);

            if (! $user) {
                User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'is_admin'          => true,
                    'email_verified_at' => now(),
                    'password'          => Hash::make($defaultPassword),
                ]);
                continue;
            }

            $updates = ['is_admin' => true];
            if (! $user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }
            if ($forceReset) {
                $updates['password'] = Hash::make($defaultPassword);
            }

            $user->fill($updates)->save();
        }
    }
}
