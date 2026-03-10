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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;

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
        // super_admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Register observers for auto-inventory creation
        Warehouse::observe(WarehouseObserver::class);
        ProductVariant::observe(ProductVariantInventoryObserver::class);
        Addon::observe(AddonInventoryObserver::class);
        
        // Register observer for auto-generating order numbers
        Order::observe(OrderObserver::class);

        // Override the password reset URL to point to the Angular wholesale frontend
        // instead of using the Laravel named route 'password.reset' (which is not registered)
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://tunerstopwholesale.com'), '/');
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
