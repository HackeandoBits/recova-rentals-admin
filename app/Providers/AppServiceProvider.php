<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Interview;
use App\Observers\InterviewObserver;
use App\Models\CalendarBlock;
use App\Observers\CalendarBlockObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Interview::observe(InterviewObserver::class);
        CalendarBlock::observe(CalendarBlockObserver::class);
    }
}
