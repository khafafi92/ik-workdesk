<?php

namespace App\Providers;

use App\Filament\Navigation\ModuleAwareNavigationManager;
use App\Http\Middleware\EnsureModuleIsEnabled;
use App\Http\Middleware\RequireLtroAccess;
use App\Http\Responses\FilamentLogoutResponse;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\WorkTask;
use App\Observers\TicketCommentObserver;
use App\Observers\TicketObserver;
use App\Observers\WorkTaskObserver;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, FilamentLogoutResponse::class);
        $this->app->scoped(NavigationManager::class, ModuleAwareNavigationManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(static function (User $user): ?bool {
            return $user->isActiveForAccess() ? null : false;
        });

        Livewire::addPersistentMiddleware([
            RequireLtroAccess::class,
            EnsureModuleIsEnabled::class,
        ]);

        Ticket::observe(TicketObserver::class);
        TicketComment::observe(TicketCommentObserver::class);
        WorkTask::observe(WorkTaskObserver::class);
    }
}
