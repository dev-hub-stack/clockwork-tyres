<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Navigation\NavigationItem;
use Filament\Facades\Filament;
use App\Modules\Inventory\Models\Warehouse;
use App\Observers\WarehouseObserver;
use App\Modules\Products\Models\ProductVariant;
use App\Observers\ProductVariantInventoryObserver;
use App\Models\Addon;
use App\Observers\AddonInventoryObserver;
use App\Modules\Orders\Models\Order;
use App\Observers\OrderObserver;

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
        // Register observers for auto-inventory creation
        Warehouse::observe(WarehouseObserver::class);
        ProductVariant::observe(ProductVariantInventoryObserver::class);
        Addon::observe(AddonInventoryObserver::class);
        
        // Register observer for auto-generating order numbers
        Order::observe(OrderObserver::class);


    }
}
