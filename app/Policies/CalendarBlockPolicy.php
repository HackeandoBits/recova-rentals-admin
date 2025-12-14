<?php

namespace App\Policies;

use App\Models\CalendarBlock;
use App\Models\User;

class CalendarBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarBlock $calendarBlock): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CalendarBlock $calendarBlock): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CalendarBlock $calendarBlock): bool
    {
        return $user->isAdmin();
    }
}
