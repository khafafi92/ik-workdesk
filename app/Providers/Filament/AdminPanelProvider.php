<?php

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\ContextualGlobalSearchProvider;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\AuthenticateFilament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
// untuk menampilkan menu ik dashboard
use Illuminate\Routing\Middleware\SubstituteBindings;
// end
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('panel')
            ->brandName('Internal 9')
            ->brandLogo(fn () => view('filament.components.brand-logo-carousel'))
            ->brandLogoHeight('3.25rem')
            ->spa()
            ->globalSearch(ContextualGlobalSearchProvider::class)
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17.5rem')
            ->collapsedSidebarWidth('5.25rem')
            ->font('Inter')
            ->darkMode(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::hex('#0073ea'),
                'success' => Color::hex('#00c875'),
                'warning' => Color::hex('#fdab3d'),
                'danger' => Color::hex('#e2445c'),
                'info' => Color::hex('#579bfc'),
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Master Data')->collapsed(),
                NavigationGroup::make()->label('Attendance Report')->collapsed(),
                NavigationGroup::make()->label('Reports'),
                NavigationGroup::make()->label('Meeting Room')->collapsed(),
                NavigationGroup::make()->label('Vehicle Booking')->collapsed(),
                NavigationGroup::make()->label('Notifications')->collapsed(),
                NavigationGroup::make()->label('Service Desk'),
                NavigationGroup::make()->label('Tasks'),
            ])
            ->navigationItems([
                NavigationItem::make('Internal 9')
                    ->url('/dashboard')
                    ->icon('heroicon-o-squares-2x2')
                    ->sort(-10)
                    ->visible(fn (): bool => Auth::check() && Auth::user()->hasRole('system-admin')),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="stylesheet" href="'.
                    asset('css/filament/admin/workdesk-theme.css').
                    '?v='.filemtime(public_path('css/filament/admin/workdesk-theme.css')).
                    '">'.
                    '<link rel="stylesheet" href="'.
                    asset('css/filament/admin/workdesk-ui-polish.css').
                    '?v='.filemtime(public_path('css/filament/admin/workdesk-ui-polish.css')).
                    '">',
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => '<script src="'.
                    asset('js/filament/admin/notification-drawer.js').
                    '?v='.filemtime(public_path('js/filament/admin/notification-drawer.js')).
                    '" defer></script>',
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.components.sidebar-collapse-footer')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => <<<'HTML'
                    <script>
                        (() => {
                            const navigationVersion = 'reports-navigation-v1'

                            if (localStorage.getItem('workdesk-navigation-version') === navigationVersion) {
                                return
                            }

                            localStorage.setItem(
                                'collapsedGroups',
                                JSON.stringify([
                                    'Master Data',
                                    'Attendance Report',
                                    'Reports',
                                    'Meeting Room',
                                    'Vehicle Booking',
                                    'Notifications',
                                    'Service Desk',
                                    'Tasks',
                                ]),
                            )
                            localStorage.setItem('workdesk-navigation-version', navigationVersion)
                        })()
                    </script>
                    HTML,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateFilament::class,
            ]);
    }
}
