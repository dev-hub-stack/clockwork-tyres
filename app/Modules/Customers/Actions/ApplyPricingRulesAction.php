<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Customers\Models\CustomerAddonCategoryPricing;
use App\Modules\Customers\Services\DealerPricingService;
use Illuminate\Support\Facades\DB;

class ApplyPricingRulesAction
{
    public function __construct(
        protected DealerPricingService $dealerPricingService
    ) {}

    /**
     * Apply brand pricing rule
     */
    public function applyBrandPricing(Customer $customer, int $brandId, array $data): CustomerBrandPricing
    {
        DB::beginTransaction();
        
        try {
            $pricing = CustomerBrandPricing::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'brand_id' => $brandId,
                ],
                [
                    'discount_type' => $data['discount_type'] ?? 'percentage',
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                    'discount_value' => $data['discount_value'] ?? 0,
                ]
            );
            
            // Clear cache
            $this->dealerPricingService->clearCustomerPricingCache($customer->id);
            
            DB::commit();
            
            return $pricing;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply model pricing rule (HIGHEST PRIORITY)
     */
    public function applyModelPricing(Customer $customer, int $modelId, array $data): CustomerModelPricing
    {
        DB::beginTransaction();
        
        try {
            $pricing = CustomerModelPricing::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'model_id' => $modelId,
                ],
                [
                    'discount_type' => $data['discount_type'] ?? 'percentage',
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                    'discount_value' => $data['discount_value'] ?? 0,
                ]
            );
            
            // Clear cache
            $this->dealerPricingService->clearCustomerPricingCache($customer->id);
            
            DB::commit();
            
            return $pricing;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply addon category pricing rule
     */
    public function applyAddonCategoryPricing(Customer $customer, int $addonCategoryId, array $data): CustomerAddonCategoryPricing
    {
        DB::beginTransaction();
        
        try {
            $pricing = CustomerAddonCategoryPricing::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'add_on_category_id' => $addonCategoryId,
                ],
                [
                    'discount_type' => $data['discount_type'] ?? 'percentage',
                    'discount_percentage' => $data['discount_percentage'] ?? 0,
                    'discount_value' => $data['discount_value'] ?? 0,
                ]
            );
            
            // Clear cache
            $this->dealerPricingService->clearCustomerPricingCache($customer->id);
            
            DB::commit();
            
            return $pricing;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove pricing rule
     */
    public function removePricingRule(string $type, int $id): bool
    {
        try {
            $deleted = match($type) {
                'brand' => CustomerBrandPricing::find($id)?->delete(),
                'model' => CustomerModelPricing::find($id)?->delete(),
                'addon_category' => CustomerAddonCategoryPricing::find($id)?->delete(),
                default => false,
            };
            
            return (bool) $deleted;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
