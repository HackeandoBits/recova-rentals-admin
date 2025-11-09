<?php

namespace App\Providers;

use App\Models\CalendarBlock;
use App\Policies\CalendarBlockPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CalendarBlock::class => CalendarBlockPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
