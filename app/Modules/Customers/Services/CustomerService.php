<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function __construct(
        protected DealerPricingService $dealerPricingService
    ) {}

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        DB::beginTransaction();
        
        try {
            $customer = Customer::create($data);
            
            // Create default address if provided
            if (!empty($data['address_data'])) {
                $this->createAddress($customer, $data['address_data']);
            }
            
            DB::commit();
            
            Log::info('Customer created', ['customer_id' => $customer->id]);
            
            return $customer->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create customer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update customer
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        DB::beginTransaction();
        
        try {
            $customer->update($data);
            
            DB::commit();
            
            Log::info('Customer updated', ['customer_id' => $customer->id]);
            
            return $customer->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update customer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete customer (soft delete)
     */
    public function deleteCustomer(Customer $customer): bool
    {
        try {
            $deleted = $customer->delete();
            
            Log::info('Customer deleted', ['customer_id' => $customer->id]);
            
            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete customer', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create address for customer
     */
    public function createAddress(Customer $customer, array $data): AddressBook
    {
        $data['customer_id'] = $customer->id;
        
        return AddressBook::create($data);
    }

    /**
     * Update address
     */
    public function updateAddress(AddressBook $address, array $data): AddressBook
    {
        $address->update($data);
        
        return $address->fresh();
    }

    /**
     * Get customer with all relationships
     */
    public function getCustomerWithRelations(int $customerId): ?Customer
    {
        return Customer::with([
            'addresses',
            'country',
            'representative',
            'brandPricingRules',
            'modelPricingRules',
            'addonCategoryPricingRules'
        ])->find($customerId);
    }

    /**
     * Search customers
     */
    public function searchCustomers(string $query, ?string $type = null, int $limit = 50): \Illuminate\Support\Collection
    {
        $customers = Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('business_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            });
        
        if ($type) {
            $customers->where('customer_type', $type);
        }
        
        return $customers->limit($limit)->get();
    }

    /**
     * Get dealers only (for quick access)
     */
    public function getDealers(int $limit = 100): \Illuminate\Support\Collection
    {
        return Customer::where('customer_type', 'dealer')
            ->orderBy('business_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate price for customer (delegates to DealerPricingService)
     */
    public function calculatePrice(
        Customer $customer,
        float $basePrice,
        ?int $modelId = null,
        ?int $brandId = null
    ): array {
        return $this->dealerPricingService->calculateProductPrice(
            $customer,
            $basePrice,
            $modelId,
            $brandId
        );
    }
}
