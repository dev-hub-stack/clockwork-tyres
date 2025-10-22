<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Navigation\NavigationItem;
use Filament\Facades\Filament;

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
        // Add custom navigation item for Products Grid
        Filament::serving(function () {
            Filament::registerNavigationItems([
                NavigationItem::make('Products Grid')
                    ->url('/admin/products/grid')
                    ->icon('heroicon-o-table-cells')
                    ->group('Products')
                    ->sort(3),
            ]);
        });
    }
}
