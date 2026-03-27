<?php

namespace App\Providers;

use App\Filament\Auth\Login as FilamentLogin;
use Illuminate\Support\ServiceProvider;
use Filament\Navigation\NavigationItem;
use Filament\Facades\Filament;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Observers\WarehouseObserver;
use App\Modules\Products\Models\ProductVariant;
use App\Observers\ProductVariantInventoryObserver;
use App\Models\Addon;
use App\Observers\AddonInventoryObserver;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\Payment;
use App\Observers\InventoryLogObserver;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductInventoryObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

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
        // Keep Livewire requests from older Filament login snapshots working across deploys.
        Livewire::component('filament.auth.pages.login', FilamentLogin::class);

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
        ProductInventory::observe(ProductInventoryObserver::class);
        InventoryLog::observe(InventoryLogObserver::class);
        Payment::observe(PaymentObserver::class);
        
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
