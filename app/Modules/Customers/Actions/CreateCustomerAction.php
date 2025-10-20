<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;

class CreateCustomerAction
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Execute the action
     */
    public function execute(array $data): Customer
    {
        // Validate and prepare data
        $customerData = $this->prepareData($data);
        
        // Create customer
        return $this->customerService->createCustomer($customerData);
    }

    /**
     * Prepare and validate data
     */
    protected function prepareData(array $data): array
    {
        // Set default customer type if not provided
        $data['customer_type'] = $data['customer_type'] ?? 'retail';
        
        // Set default status
        $data['status'] = $data['status'] ?? 'active';
        
        // Clean phone number
        if (!empty($data['phone'])) {
            $data['phone'] = preg_replace('/[^0-9+]/', '', $data['phone']);
        }
        
        // Clean email
        if (!empty($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        
        return $data;
    }
}
