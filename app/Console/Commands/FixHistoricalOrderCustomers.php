<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;

class FixHistoricalOrderCustomers extends Command
{
    protected $signature = 'fix:historical-order-customers 
                            {--connection=tunerstop_source : Source database connection}
                            {--dry-run : Simulate without saving}
                            {--mark-completed : Mark all historical orders as completed and paid}';

    protected $description = 'Fix historical orders by assigning correct customers based on billing email and optionally mark as completed/paid';

    protected string $sourceConnection;
    protected bool $dryRun;
    protected int $updatedOrders = 0;
    protected int $createdCustomers = 0;
    protected int $createdAddresses = 0;
    protected int $skippedOrders = 0;
    protected int $markedCompleted = 0;
    protected int $markedPaid = 0;
    protected array $errors = [];

    public function handle(): int
    {
        $this->sourceConnection = $this->option('connection');
        $this->dryRun = $this->option('dry-run');
        $markCompleted = $this->option('mark-completed');

        $this->info('');
        $this->info('🔧 Fix Historical Order Customers');
        $this->info('=====================================');
        $this->info("Source Connection: {$this->sourceConnection}");

        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be saved');
        }

        if ($markCompleted) {
            $this->info('📋 Will mark all historical orders as COMPLETED and PAID');
        }

        // Test source connection
        try {
            DB::connection($this->sourceConnection)->getPdo();
            $this->info("✅ Source database connection successful");
        } catch (\Exception $e) {
            $this->error("❌ Cannot connect to source database: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('');

        // Get all historical orders
        $historicalOrders = Order::where('external_source', 'tunerstop_historical')->get();
        $this->info("📦 Found {$historicalOrders->count()} historical orders to process");

        $bar = $this->output->createProgressBar($historicalOrders->count());
        $bar->start();

        foreach ($historicalOrders as $order) {
            try {
                $this->processOrder($order, $markCompleted);
            } catch (\Exception $e) {
                $this->errors[] = "Order {$order->id}: " . $e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info('');
        $this->info('');

        $this->printSummary();

        return Command::SUCCESS;
    }

    protected function processOrder(Order $order, bool $markCompleted): void
    {
        // Get billing data from source
        $billing = DB::connection($this->sourceConnection)
            ->table('billing')
            ->where('order_id', $order->external_order_id)
            ->first();

        $updated = false;

        if ($billing && $billing->email) {
            // Find or create customer by email
            $customer = Customer::where('email', $billing->email)->first();

            if (!$customer && !$this->dryRun) {
                // Create new customer
                $customer = Customer::create([
                    'first_name' => $billing->first_name ?? 'Guest',
                    'last_name' => $billing->last_name ?? 'Customer',
                    'email' => $billing->email,
                    'phone' => $billing->phone,
                    'customer_type' => 'retail',
                    'country' => $billing->country ?? 'UAE',
                    'city' => $billing->city,
                    'is_active' => true,
                    'notes' => 'Created from historical order fix',
                    'created_at' => $billing->created_at,
                    'updated_at' => now(),
                ]);
                $this->createdCustomers++;

                // Create billing address
                $existingAddress = AddressBook::where('customer_id', $customer->id)
                    ->where('address', $billing->address)
                    ->where('city', $billing->city)
                    ->first();

                if (!$existingAddress) {
                    AddressBook::create([
                        'customer_id' => $customer->id,
                        'address_type' => 1, // billing
                        'nickname' => 'Billing Address',
                        'first_name' => $billing->first_name,
                        'last_name' => $billing->last_name,
                        'address' => $billing->address,
                        'city' => $billing->city,
                        'state' => null,
                        'country' => $billing->country ?? 'UAE',
                        'zip_code' => null,
                        'phone_no' => $billing->phone,
                        'email' => $billing->email,
                        'created_at' => $billing->created_at,
                        'updated_at' => $billing->updated_at,
                    ]);
                    $this->createdAddresses++;
                }
            }

            // Update order customer_id if different
            if ($customer && $order->customer_id !== $customer->id) {
                if (!$this->dryRun) {
                    $order->customer_id = $customer->id;
                    $updated = true;
                }
                $this->updatedOrders++;
            }
        } else {
            $this->skippedOrders++;
        }

        // Mark as completed and paid if requested
        if ($markCompleted && !$this->dryRun) {
            if ($order->order_status !== OrderStatus::COMPLETED && $order->order_status !== OrderStatus::CANCELLED) {
                $order->order_status = OrderStatus::COMPLETED;
                $this->markedCompleted++;
                $updated = true;
            }
            if ($order->payment_status !== PaymentStatus::PAID) {
                $order->payment_status = PaymentStatus::PAID;
                $this->markedPaid++;
                $updated = true;
            }
        }

        if ($updated && !$this->dryRun) {
            $order->save();
        }
    }

    protected function printSummary(): void
    {
        $this->info('=====================================');
        $this->info('📊 FIX SUMMARY');
        $this->info('=====================================');

        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN - No data was saved');
        }

        $this->info("✅ Orders Updated:      {$this->updatedOrders}");
        $this->info("✅ Customers Created:   {$this->createdCustomers}");
        $this->info("✅ Addresses Created:   {$this->createdAddresses}");
        $this->info("✅ Marked Completed:    {$this->markedCompleted}");
        $this->info("✅ Marked Paid:         {$this->markedPaid}");
        $this->info("⏭️  Skipped (no email): {$this->skippedOrders}");

        if (count($this->errors) > 0) {
            $this->info('');
            $this->warn('⚠️ Errors encountered:');
            foreach (array_slice($this->errors, 0, 10) as $error) {
                $this->error("  - {$error}");
            }
            if (count($this->errors) > 10) {
                $this->error("  ... and " . (count($this->errors) - 10) . " more errors");
            }
        }

        $this->info('');
        $this->info('🎉 Fix complete!');
    }
}
