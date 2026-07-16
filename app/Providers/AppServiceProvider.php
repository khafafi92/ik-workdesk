<?php

namespace App\Providers;

use App\Http\Responses\FilamentLogoutResponse;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\WorkTask;
use App\Observers\TicketCommentObserver;
use App\Observers\TicketObserver;
use App\Observers\WorkTaskObserver;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, FilamentLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Ticket::observe(TicketObserver::class);
        TicketComment::observe(TicketCommentObserver::class);
        WorkTask::observe(WorkTaskObserver::class);
    }
}
