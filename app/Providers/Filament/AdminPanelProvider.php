<?php

namespace App\Providers\Filament;

use App\Filament\Support\PanelAccess;
use Filament\Http\Middleware\Authenticate;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->colors([
                'primary' => Color::hex('#e91e63'), // Pink/Magenta accent
                'gray' => Color::Slate,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
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
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Sales')->icon('heroicon-o-document-currency-dollar'),
                \Filament\Navigation\NavigationGroup::make('Inventory')->icon('heroicon-o-archive-box'),
                \Filament\Navigation\NavigationGroup::make('Customers')->icon('heroicon-o-user-group'),
                \Filament\Navigation\NavigationGroup::make('Products')->icon('heroicon-o-cube'),
                \Filament\Navigation\NavigationGroup::make('Reports')->icon('heroicon-o-chart-bar'),
                \Filament\Navigation\NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth'),
                \Filament\Navigation\NavigationGroup::make('Administration')->icon('heroicon-o-shield-check'),
            ])
            ->navigationItems([
                NavigationItem::make('Product Images')
                    ->url('/admin/products/images')
                    ->icon('heroicon-o-photo')
                    ->group('Products')
                    ->sort(5)
                    ->visible(fn () => PanelAccess::canAccessOperationalSurface('view_products')),
                
                // Reports Group Header
                NavigationItem::make('Sales Dashboard')
                    ->url('/admin/sales-dashboard')
                    ->icon('heroicon-o-chart-bar')
                    ->group('Reports')
                    ->sort(10)
                    ->visible(fn () => (auth()->user()?->can('view_reports') ?? false) && (auth()->user()?->can('view_sales_reports') ?? false)),
                
                NavigationItem::make('Customer Analytics')
                    ->url('/admin/customer-analytics')
                    ->icon('heroicon-o-users')
                    ->group('Reports')
                    ->sort(11)
                    ->visible(fn () => (auth()->user()?->can('view_reports') ?? false) && (auth()->user()?->can('view_sales_reports') ?? false)),
                
                NavigationItem::make('Product Performance')
                    ->url('/admin/product-performance')
                    ->icon('heroicon-o-cube')
                    ->group('Reports')
                    ->sort(12)
                    ->visible(fn () => (auth()->user()?->can('view_reports') ?? false) && (auth()->user()?->can('view_sales_reports') ?? false)),
                
                NavigationItem::make('Geographic Sales')
                    ->url('/admin/geographic-sales')
                    ->icon('heroicon-o-globe-alt')
                    ->group('Reports')
                    ->sort(13)
                    ->visible(fn () => (auth()->user()?->can('view_reports') ?? false) && (auth()->user()?->can('view_sales_reports') ?? false)),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
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
