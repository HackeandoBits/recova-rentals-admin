<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Interview $i): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_admin; // ajustá si querés permitir a otros
    }

    public function update(User $user, Interview $i): bool
    {
        if ($i->status === 'confirmed') {
            return (bool) $user->is_admin; // ← clave: confirmadas solo admin
        }

        return (bool) $user->is_admin; // o true si querés que otros editen no-confirmadas
    }

    public function delete(User $user, Interview $i): bool
    {
        return (bool) $user->is_admin;
    }
}
