<?php

namespace App\Providers;

use App\Models\CalendarBlock;
use App\Models\Interview;
use App\Observers\CalendarBlockObserver;
use App\Observers\InterviewObserver;
use Illuminate\Support\ServiceProvider;

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
