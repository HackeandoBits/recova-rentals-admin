<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function viewAny(User $user): bool
    {
        // Todos pueden ver el calendario
        return true;
    }

    public function view(User $user, Interview $interview): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Interview $interview): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Interview $interview): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
