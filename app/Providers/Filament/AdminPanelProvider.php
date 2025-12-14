<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        \Log::info('AdminPanelProvider::panel() called');
        
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::hex('#e91e63'), // Pink/Magenta accent
                'gray' => Color::Slate,
            ])
            ->darkMode(false)
            // Enable collapsible sidebar on desktop (like Tunerstop)
            ->sidebarCollapsibleOnDesktop()
            // Optional: Make sidebar collapsed by default
            // ->sidebarFullyCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Widgets disabled - using custom dashboard page with Alpine.js
            ])
            // Set custom dashboard as home page
            ->homeUrl('/admin/dashboard')
            ->navigationItems([
                NavigationItem::make('Product Images')
                    ->url('/admin/products/images')
                    ->icon('heroicon-o-photo')
                    ->group('Products')
                    ->sort(5)
                    ->visible(fn () => auth()->user()->can('view_products')),
                
                // Reports Group Header
                NavigationItem::make('Sales Dashboard')
                    ->url('/admin/sales-dashboard')
                    ->icon('heroicon-o-chart-bar')
                    ->group('Reports')
                    ->sort(10),
                
                NavigationItem::make('Customer Analytics')
                    ->url('/admin/customer-analytics')
                    ->icon('heroicon-o-users')
                    ->group('Reports')
                    ->sort(11),
                
                NavigationItem::make('Product Performance')
                    ->url('/admin/product-performance')
                    ->icon('heroicon-o-cube')
                    ->group('Reports')
                    ->sort(12),
                
                NavigationItem::make('Geographic Sales')
                    ->url('/admin/geographic-sales')
                    ->icon('heroicon-o-globe-alt')
                    ->group('Reports')
                    ->sort(13),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
