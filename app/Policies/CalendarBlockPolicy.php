<?php

namespace App\Policies;

use App\Models\CalendarBlock;
use App\Models\User;

class CalendarBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, CalendarBlock $block): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, CalendarBlock $block): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, CalendarBlock $block): bool
    {
        return $user->is_admin;
    }

    public function restore(User $user, CalendarBlock $block): bool
    {
        return $user->is_admin;
    }

    public function forceDelete(User $user, CalendarBlock $block): bool
    {
        return false;
    }
}
