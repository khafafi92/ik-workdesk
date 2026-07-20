<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\AuthenticateFilament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
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
        //

        return $panel
            ->default()
            ->id('admin')
            ->path('panel')
            ->brandName('Internal 9')
            ->brandLogo(fn () => view('filament.components.brand-logo-carousel'))
            ->brandLogoHeight('3.25rem')
            ->spa()
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            //->topNavigation() // Tambahkan di sini (rantai utama)
            ->font('Roboto')  // Pindahkan ke sini (rantai utama)
            ->colors([
                'primary' => Color::hex('#0073ea'),
                'success' => Color::hex('#00c875'),
                'warning' => Color::hex('#fdab3d'),
                'danger' => Color::hex('#e2445c'),
                'gray' => Color::Slate,
            ])
            ->navigationItems([
                NavigationItem::make('Internal 9')
                    ->url('/dashboard')
                    ->icon('heroicon-o-squares-2x2')
                    ->sort(-10)
                    ->visible(fn (): bool => Auth::check() && Auth::user()->hasRole('superadmin')),
            ])

        //
        //     return $panel
        // ->default()
        // ->id('admin')
        // ->path('panel')
        // ->brandName('Internal 9')
        // ->brandName('IK Workdesk')
        // // ->login()
        // // ->spa()
        // // ->maxContentWidth(Width::Full)
        // // ->sidebarCollapsibleOnDesktop()
        // // ->navigationItems([
        // //     NavigationItem::make('Beranda')
        // //         ->url('/dashboard')
        // //         ->icon('heroicon-o-home')
        // //         ->sort(-10)
        // //         ->font('Roboto'),
        // // ])
        // // ->colors([
        // //     'primary' => Color::hex('#0073ea'), // Biru khas Monday.com
        // //             'success' => Color::hex('#00c875'), // Hijau 'Done'
        // //             'warning' => Color::hex('#fdab3d'), // Jingga 'Working on it'
        // //             'danger'  => Color::hex('#e2445c'), // Merah jambu 'Stuck'
        // //             'gray'    => Color::Slate,

        //     // 'primary' => Color::Blue,
        //     // 'success' => Color::Emerald,
        //     // 'warning' => Color::Amber,
        //     // 'danger' => Color::Rose,
        //     // 'gray' => Color::Slate,
        // ])
            ->renderHook(
    PanelsRenderHook::STYLES_AFTER,
    fn (): string => '<link rel="stylesheet" href="' .
        asset('css/filament/admin/workdesk-theme.css') .
        '?v=' . filemtime(public_path('css/filament/admin/workdesk-theme.css')) .
        '">',
)
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.components.sidebar-collapse-footer')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
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
                AuthenticateFilament::class,
            ]);
    }
}
