<?php

namespace App\Providers\Filament;

use Filament\Navigation\NavigationItem;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Blade;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
    ->default()
    ->id('admin')
    ->path('panel')
    ->brandName('IK Workdesk')
    ->login()
    ->spa()
    ->maxContentWidth(Width::Full)
    ->sidebarCollapsibleOnDesktop()
    ->navigationItems([
        NavigationItem::make('IK Dashboard')
            ->url('/dashboard')
            ->icon('heroicon-o-home')
            ->sort(-10),
    ])
    ->colors([
        'primary' => Color::Blue,
        'success' => Color::Emerald,
        'warning' => Color::Amber,
        'danger' => Color::Rose,
        'gray' => Color::Slate,
    ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render(
                    '<link rel="stylesheet" href="{{ asset(\'css/filament/admin/workdesk-theme.css\') }}">'
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
                Authenticate::class,
            ]);
    }
}
