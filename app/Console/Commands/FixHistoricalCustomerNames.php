<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Customers\Models\Customer;

class FixHistoricalCustomerNames extends Command
{
    protected $signature = 'fix:historical-customer-names 
                            {--connection=tunerstop_source : Source database connection}
                            {--dry-run : Simulate without saving}';

    protected $description = 'Fix customer names from billing/shipping data for historical imports';

    protected string $sourceConnection;
    protected bool $dryRun;
    protected int $updatedCustomers = 0;
    protected int $skippedCustomers = 0;

    public function handle(): int
    {
        $this->sourceConnection = $this->option('connection');
        $this->dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('🔧 Fixing Historical Customer Names');
        $this->info('=====================================');
        $this->info("Source Connection: {$this->sourceConnection}");
        if ($this->dryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be saved');
        }

        // Test source connection
        try {
            DB::connection($this->sourceConnection)->getPdo();
            $this->info('✅ Source database connection successful');
        } catch (\Exception $e) {
            $this->error('❌ Cannot connect to source database: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Get all customers with null/empty first_name or last_name
        $customers = Customer::where(function($q) {
            $q->whereNull('first_name')
              ->orWhere('first_name', '')
              ->orWhere('first_name', 'Guest')
              ->orWhere('first_name', 'Unknown')
              ->orWhereNull('last_name')
              ->orWhere('last_name', '')
              ->orWhere('last_name', 'Customer');
        })->whereNotNull('email')->get();

        $this->info("Found {$customers->count()} customers to fix");
        $this->info('');

        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        foreach ($customers as $customer) {
            $this->fixCustomerName($customer);
            $bar->advance();
        }

        $bar->finish();
        $this->info('');
        $this->info('');

        // Print summary
        $this->info('=====================================');
        $this->info('📊 FIX SUMMARY');
        $this->info('=====================================');
        if ($this->dryRun) {
            $this->info('🔍 DRY RUN - No data was saved');
        }
        $this->info("✅ Customers Updated: {$this->updatedCustomers}");
        $this->info("⚠️  Customers Skipped: {$this->skippedCustomers}");

        $this->info('');
        $this->info('🎉 Fix complete!');

        return Command::SUCCESS;
    }

    protected function fixCustomerName(Customer $customer): void
    {
        // Get billing data from source by email
        $billing = DB::connection($this->sourceConnection)
            ->table('billing')
            ->where('email', $customer->email)
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '')
            ->orderBy('created_at', 'desc')
            ->first();

        // If no billing data, try shipping
        if (!$billing) {
            $billing = DB::connection($this->sourceConnection)
                ->table('shipping')
                ->where('email', $customer->email)
                ->whereNotNull('first_name')
                ->where('first_name', '!=', '')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (!$billing || !$billing->first_name) {
            $this->skippedCustomers++;
            return;
        }

        $firstName = trim($billing->first_name);
        $lastName = trim($billing->last_name ?? '');

        // Update customer if needed
        $needsUpdate = false;
        $updates = [];

        if (empty($customer->first_name) || $customer->first_name === 'Guest' || $customer->first_name === 'Unknown') {
            $updates['first_name'] = $firstName;
            $needsUpdate = true;
        }

        if (empty($customer->last_name) || $customer->last_name === 'Customer') {
            $updates['last_name'] = $lastName;
            $needsUpdate = true;
        }

        // Also update phone, city, country if missing
        if (empty($customer->phone) && !empty($billing->phone)) {
            $updates['phone'] = $billing->phone;
            $needsUpdate = true;
        }
        if (empty($customer->city) && !empty($billing->city)) {
            $updates['city'] = $billing->city;
            $needsUpdate = true;
        }
        if (empty($customer->country) && !empty($billing->country)) {
            $updates['country'] = $billing->country;
            $needsUpdate = true;
        }

        if ($needsUpdate && !$this->dryRun) {
            $customer->update($updates);
        }

        if ($needsUpdate) {
            $this->updatedCustomers++;
        } else {
            $this->skippedCustomers++;
        }
    }
}
