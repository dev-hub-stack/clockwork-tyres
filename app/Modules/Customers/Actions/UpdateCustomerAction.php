<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;

class UpdateCustomerAction
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Execute the action
     */
    public function execute(Customer $customer, array $data): Customer
    {
        // Validate and prepare data
        $customerData = $this->prepareData($data);
        
        // Update customer
        return $this->customerService->updateCustomer($customer, $customerData);
    }

    /**
     * Prepare and validate data
     */
    protected function prepareData(array $data): array
    {
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
