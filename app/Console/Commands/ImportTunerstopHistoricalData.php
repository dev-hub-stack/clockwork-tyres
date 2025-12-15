<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\AddressBook;
use Carbon\Carbon;

class ImportTunerstopHistoricalData extends Command
{
    protected $signature = 'import:tunerstop-historical 
                            {--connection=tunerstop_source : Database connection name}
                            {--batch-size=500 : Batch size for chunked processing}
                            {--skip-products : Skip importing products/variants}
                            {--skip-customers : Skip importing customers}
                            {--orders-only : Only import orders}
                            {--dry-run : Simulate import without saving}
                            {--from-date= : Start date for orders (Y-m-d)}
                            {--to-date= : End date for orders (Y-m-d)}';

    protected $description = 'Import historical data from TunerStop database for reporting';

    protected string $sourceConnection;
    protected int $batchSize;
    protected bool $dryRun;
    protected ?Carbon $fromDate = null;
    protected ?Carbon $toDate = null;

    protected int $importedOrders = 0;
    protected int $importedItems = 0;
    protected int $importedBrands = 0;
    protected int $importedModels = 0;
    protected int $importedProducts = 0;
    protected int $importedVariants = 0;
    protected int $importedCustomers = 0;
    protected int $importedAddresses = 0;
    protected int $skippedOrders = 0;
    protected array $errors = [];
    protected array $customerEmailMap = []; // email => customer_id mapping

    /**
     * TunerStop status mapping
     */
    protected array $statusMap = [
        -1 => OrderStatus::PENDING,
        0 => OrderStatus::PENDING,
        1 => OrderStatus::COMPLETED,
        2 => OrderStatus::CANCELLED,
    ];

    public function handle(): int
    {
        $this->sourceConnection = $this->option('connection');
        $this->batchSize = (int) $this->option('batch-size');
        $this->dryRun = $this->option('dry-run');

        if ($this->option('from-date')) {
            $this->fromDate = Carbon::parse($this->option('from-date'));
        }
        if ($this->option('to-date')) {
            $this->toDate = Carbon::parse($this->option('to-date'));
        }

        $this->info('');
        $this->info('🚀 TunerStop Historical Data Import');
        $this->info('=====================================');
        $this->info("Source Connection: {$this->sourceConnection}");
        $this->info("Batch Size: {$this->batchSize}");
        
        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be saved');
        }

        if ($this->fromDate) {
            $this->info("From Date: {$this->fromDate->format('Y-m-d')}");
        }
        if ($this->toDate) {
            $this->info("To Date: {$this->toDate->format('Y-m-d')}");
        }

        // Test connection
        try {
            DB::connection($this->sourceConnection)->getPdo();
            $this->info("✅ Source database connection successful");
        } catch (\Exception $e) {
            $this->error("❌ Cannot connect to source database: " . $e->getMessage());
            $this->error("Make sure '{$this->sourceConnection}' is configured in config/database.php");
            return Command::FAILURE;
        }

        $this->info('');

        if (!$this->option('orders-only')) {
            if (!$this->option('skip-products')) {
                // Import reference data
                $this->importBrands();
                $this->importModels();
                $this->importFinishes();
                
                // Import products
                $this->importProducts();
                $this->importProductVariants();
            }

            if (!$this->option('skip-customers')) {
                // Import customers (from billing data)
                $this->importCustomers();
            }
        }

        // Import orders
        $this->importOrders();

        $this->printSummary();

        return Command::SUCCESS;
    }

    protected function importBrands(): void
    {
        $this->info('📦 Importing Brands...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        $brands = DB::connection($this->sourceConnection)
            ->table('brands')
            ->select('id', 'name', 'image', 'created_at', 'updated_at')
            ->get();

        foreach ($brands as $brand) {
            if (!$this->dryRun) {
                // Check if brand exists by name first (to avoid unique constraint violation)
                $existingBrand = Brand::where('name', $brand->name)->first();
                
                if ($existingBrand) {
                    // Update existing brand with external_id if not set
                    if (!$existingBrand->external_id) {
                        $existingBrand->update([
                            'external_id' => $brand->id,
                            'external_source' => 'tunerstop',
                        ]);
                    }
                } else {
                    // Create new brand
                    Brand::create([
                        'external_id' => $brand->id,
                        'external_source' => 'tunerstop',
                        'name' => $brand->name,
                        'logo_url' => $brand->image,
                        'is_active' => true,
                        'created_at' => $brand->created_at,
                        'updated_at' => $brand->updated_at,
                    ]);
                }
            }
            $this->importedBrands++;
            $bar->advance();
        }

        $bar->finish();
        $this->info(" ✅ {$this->importedBrands} brands");
    }

    protected function importModels(): void
    {
        $this->info('📦 Importing Models...');
        $bar = $this->output->createProgressBar();
        $bar->start();

        $models = DB::connection($this->sourceConnection)
            ->table('models')
            ->select('id', 'name', 'created_at', 'updated_at')
            ->get();

        foreach ($models as $model) {
            if (!$this->dryRun) {
                // Models table doesn't have external_id, just match by name
                ProductModel::firstOrCreate(
                    ['name' => $model->name],
                    [
                        'image' => $model->image ?? null,
                        'created_at' => $model->created_at,
                        'updated_at' => $model->updated_at,
                    ]
                );
            }
            $this->importedModels++;
            $bar->advance();
        }

        $bar->finish();
        $this->info(" ✅ {$this->importedModels} models");
    }

    protected function importFinishes(): void
    {
        $this->info('📦 Importing Finishes...');
        $count = 0;

        $finishes = DB::connection($this->sourceConnection)
            ->table('finishes')
            ->select('id', 'finish', 'created_at', 'updated_at')
            ->get();

        foreach ($finishes as $finish) {
            if (!$this->dryRun) {
                // Finishes table doesn't have external_id, just match by name (finish column)
                Finish::firstOrCreate(
                    ['finish' => $finish->finish],
                    [
                        'created_at' => $finish->created_at,
                        'updated_at' => $finish->updated_at,
                    ]
                );
            }
            $count++;
        }

        $this->info("   ✅ {$count} finishes");
    }

    protected function importProducts(): void
    {
        $this->info('📦 Importing Products...');

        $total = DB::connection($this->sourceConnection)
            ->table('products')
            ->whereNull('deleted_at')
            ->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::connection($this->sourceConnection)
            ->table('products')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($products) use ($bar) {
                foreach ($products as $product) {
                    if (!$this->dryRun) {
                        // Get related records from TunerStop source
                        $tsBrand = DB::connection($this->sourceConnection)->table('brands')->where('id', $product->brand_id)->first();
                        $tsModel = DB::connection($this->sourceConnection)->table('models')->where('id', $product->model_id)->first();
                        $tsFinish = DB::connection($this->sourceConnection)->table('finishes')->where('id', $product->finish_id)->first();
                        
                        // Match by name in CRM (since models and finishes don't have external_id)
                        $brand = $tsBrand ? Brand::where('name', $tsBrand->name)->first() : null;
                        $model = $tsModel ? ProductModel::where('name', $tsModel->name)->first() : null;
                        $finish = $tsFinish ? Finish::where('finish', $tsFinish->finish)->first() : null;

                        Product::updateOrCreate(
                            ['external_product_id' => $product->id, 'external_source' => 'tunerstop'],
                            [
                                'name' => $product->name,
                                'sku' => $product->sku ?? ('TS-P-' . $product->id),
                                'price' => $product->price ?? 0,
                                'brand_id' => $brand?->id,
                                'model_id' => $model?->id,
                                'finish_id' => $finish?->id,
                                'status' => $product->status ?? 1,
                                'created_at' => $product->created_at,
                                'updated_at' => $product->updated_at,
                            ]
                        );
                    }
                    $this->importedProducts++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->info(" ✅ {$this->importedProducts} products");
    }

    protected function importProductVariants(): void
    {
        $this->info('📦 Importing Product Variants...');

        $total = DB::connection($this->sourceConnection)
            ->table('product_variants')
            ->whereNull('deleted_at')
            ->count();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::connection($this->sourceConnection)
            ->table('product_variants')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($variants) use ($bar) {
                foreach ($variants as $variant) {
                    if (!$this->dryRun) {
                        $product = Product::where('external_product_id', $variant->product_id)
                            ->where('external_source', 'tunerstop')
                            ->first();

                        if ($product) {
                            // Check if variant already exists by external_variant_id OR sku
                            $existingVariant = ProductVariant::where(function($query) use ($variant) {
                                $query->where('external_variant_id', $variant->id)
                                      ->where('external_source', 'tunerstop');
                            })->orWhere('sku', $variant->sku)->first();
                            
                            if ($existingVariant) {
                                // Update existing variant
                                $existingVariant->update([
                                    'external_variant_id' => $variant->id,
                                    'external_source' => 'tunerstop',
                                    'product_id' => $product->id,
                                    'size' => $variant->size,
                                    'rim_diameter' => $variant->rim_diameter,
                                    'rim_width' => $variant->rim_width,
                                    'bolt_pattern' => $variant->bolt_pattern,
                                    'offset' => $variant->offset,
                                    'hub_bore' => $variant->hub_bore,
                                    'weight' => $variant->weight,
                                    'cost' => is_numeric($variant->cost) ? $variant->cost : null,
                                    'price' => $variant->uae_retail_price ?? 0,
                                    'uae_retail_price' => $variant->uae_retail_price ?? 0,
                                ]);
                            } else {
                                // Create new variant
                                ProductVariant::create([
                                    'external_variant_id' => $variant->id,
                                    'external_source' => 'tunerstop',
                                    'product_id' => $product->id,
                                    'sku' => $variant->sku,
                                    'size' => $variant->size,
                                    'rim_diameter' => $variant->rim_diameter,
                                    'rim_width' => $variant->rim_width,
                                    'bolt_pattern' => $variant->bolt_pattern,
                                    'offset' => $variant->offset,
                                    'hub_bore' => $variant->hub_bore,
                                    'weight' => $variant->weight,
                                    'cost' => is_numeric($variant->cost) ? $variant->cost : null,
                                    'price' => $variant->uae_retail_price ?? 0,
                                    'uae_retail_price' => $variant->uae_retail_price ?? 0,
                                    'created_at' => $variant->created_at,
                                    'updated_at' => $variant->updated_at,
                                ]);
                            }
                        }
                    }
                    $this->importedVariants++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->info(" ✅ {$this->importedVariants} variants");
    }

    protected function importCustomers(): void
    {
        $this->info('📦 Importing Customers from Billing Data...');

        // Get unique customers from billing table
        $uniqueCustomers = DB::connection($this->sourceConnection)
            ->table('billing')
            ->select(
                'email',
                DB::raw('MIN(first_name) as first_name'),
                DB::raw('MIN(last_name) as last_name'),
                DB::raw('MIN(phone) as phone'),
                DB::raw('MIN(country) as country'),
                DB::raw('MIN(city) as city'),
                DB::raw('MIN(created_at) as customer_since'),
                DB::raw('COUNT(DISTINCT order_id) as order_count')
            )
            ->whereNotNull('email')
            ->groupBy('email')
            ->orderBy('customer_since')
            ->get();

        $bar = $this->output->createProgressBar($uniqueCustomers->count());
        $bar->start();

        foreach ($uniqueCustomers as $customerData) {
            try {
                if (!$this->dryRun) {
                    // Check if customer already exists
                    $customer = Customer::where('email', $customerData->email)->first();

                    if (!$customer) {
                        $customer = Customer::create([
                            'first_name' => $customerData->first_name ?? 'Guest',
                            'last_name' => $customerData->last_name ?? 'Customer',
                            'email' => $customerData->email,
                            'phone' => $customerData->phone,
                            'customer_type' => 'retail',
                            'country' => $customerData->country,
                            'city' => $customerData->city,
                            'is_active' => true,
                            'notes' => 'Imported from TunerStop historical data (' . $customerData->order_count . ' orders)',
                            'created_at' => $customerData->customer_since,
                            'updated_at' => now(),
                        ]);

                        // Import addresses for this customer
                        $this->importCustomerAddresses($customer, $customerData->email);
                    }

                    // Store customer mapping for order linking
                    $this->customerEmailMap[$customerData->email] = $customer->id;
                }

                $this->importedCustomers++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->errors[] = "Customer {$customerData->email}: " . $e->getMessage();
            }
        }

        $bar->finish();
        $this->info(" ✅ {$this->importedCustomers} customers");
    }

    protected function importCustomerAddresses(Customer $customer, string $email): void
    {
        // Get all billing addresses for this customer
        $billingAddresses = DB::connection($this->sourceConnection)
            ->table('billing')
            ->where('email', $email)
            ->orderBy('created_at')
            ->get();

        $addressCount = 0;

        foreach ($billingAddresses as $index => $billing) {
            // Skip if address is empty
            if (empty($billing->address)) continue;

            // Check if address already exists (to avoid duplicates)
            $existingAddress = AddressBook::where('customer_id', $customer->id)
                ->where('address', $billing->address)
                ->where('city', $billing->city)
                ->first();

            if ($existingAddress) continue;

            // Create billing address (address_type: 1=billing, 2=shipping based on CRM enum)
            AddressBook::create([
                'customer_id' => $customer->id,
                'address_type' => 1, // billing
                'nickname' => $index === 0 ? 'Primary Billing' : 'Billing Address ' . ($index + 1),
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

            $addressCount++;
            $this->importedAddresses++;
        }

        // Get shipping addresses if different from billing
        $shippingAddresses = DB::connection($this->sourceConnection)
            ->table('shipping')
            ->where('email', $email)
            ->whereNotNull('address')
            ->get();

        foreach ($shippingAddresses as $shipping) {
            // Check if this shipping address is different from billing
            $isDifferent = AddressBook::where('customer_id', $customer->id)
                ->where('address', $shipping->address)
                ->where('city', $shipping->city)
                ->doesntExist();

            if ($isDifferent) {
                AddressBook::create([
                    'customer_id' => $customer->id,
                    'address_type' => 2, // shipping
                    'nickname' => 'Shipping Address',
                    'first_name' => $shipping->first_name,
                    'last_name' => $shipping->last_name,
                    'address' => $shipping->address,
                    'city' => $shipping->city,
                    'state' => null,
                    'country' => $shipping->country ?? 'UAE',
                    'zip_code' => null,
                    'phone_no' => $shipping->phone,
                    'email' => $shipping->email,
                    'created_at' => $shipping->created_at,
                    'updated_at' => $shipping->updated_at,
                ]);

                $addressCount++;
                $this->importedAddresses++;
            }
        }
    }

    protected function importOrders(): void
    {
        $this->info('📦 Importing Orders...');

        // Load customer email mapping if not already loaded
        if (empty($this->customerEmailMap) && !$this->dryRun) {
            $customers = Customer::whereNotNull('email')->get(['id', 'email']);
            foreach ($customers as $customer) {
                $this->customerEmailMap[$customer->email] = $customer->id;
            }
        }

        $query = DB::connection($this->sourceConnection)
            ->table('orders')
            ->where('status', '>=', 0);

        if ($this->fromDate) {
            $query->where('created_at', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $query->where('created_at', '<=', $this->toDate);
        }

        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')
            ->chunk($this->batchSize, function ($orders) use ($bar) {
                foreach ($orders as $order) {
                    try {
                        $this->importSingleOrder($order);
                    } catch (\Exception $e) {
                        $this->errors[] = "Order {$order->id}: " . $e->getMessage();
                        $this->skippedOrders++;
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->info(" ✅ {$this->importedOrders} orders, {$this->importedItems} items");
    }

    protected function importSingleOrder(object $tsOrder): void
    {
        // Check if already imported
        if (!$this->dryRun) {
            $existing = Order::where('external_order_id', $tsOrder->id)
                ->where('external_source', 'tunerstop_historical')
                ->exists();

            if ($existing) return;
        }

        // Get customer from billing data
        $billing = DB::connection($this->sourceConnection)
            ->table('billing')
            ->where('order_id', $tsOrder->id)
            ->first();

        $customer = null;
        if ($billing && isset($this->customerEmailMap[$billing->email])) {
            $customer = !$this->dryRun 
                ? Customer::find($this->customerEmailMap[$billing->email])
                : new Customer(['id' => 0]);
        }

        // Fallback to default if no customer found
        if (!$customer) {
            $customer = !$this->dryRun 
                ? $this->getOrCreateDefaultRetailCustomer()
                : new Customer(['id' => 0]);
        }

        $orderStatus = $this->statusMap[$tsOrder->status] ?? OrderStatus::PENDING;

        // Auto-complete old orders (before Nov 2025) unless cancelled
        // This handles the requirement to mark fulfillment as completed for historical orders until Oct 2025
        if ($orderStatus !== OrderStatus::CANCELLED) {
            $orderDate = Carbon::parse($tsOrder->created_at);
            if ($orderDate->lt('2025-11-01')) {
                $orderStatus = OrderStatus::COMPLETED;
            }
        }

        $paymentStatus = PaymentStatus::PENDING;
        if ($tsOrder->paid_amount >= $tsOrder->total) {
            $paymentStatus = PaymentStatus::PAID;
        } elseif ($tsOrder->paid_amount > 0) {
            $paymentStatus = PaymentStatus::PARTIAL;
        }

        if (!$this->dryRun) {
            // Generate unique order number (source has duplicates, so append ID)
            $orderNumber = 'TS-' . $tsOrder->id . '-' . substr($tsOrder->order_number, 0, 10);
            
            $order = Order::create([
                'document_type' => DocumentType::INVOICE,
                'order_number' => $orderNumber,
                'external_order_id' => $tsOrder->id,
                'external_source' => 'tunerstop_historical',
                'customer_id' => $customer->id,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'sub_total' => $tsOrder->sub_total ?? 0,
                'tax' => 0,
                'vat' => $tsOrder->vat ?? 0,
                'shipping' => $tsOrder->shipping ?? 0,
                'discount' => $tsOrder->discount ?? 0,
                'total' => $tsOrder->total ?? 0,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'vehicle_year' => $tsOrder->vehicle_year,
                'vehicle_make' => $tsOrder->vehicle_make,
                'vehicle_model' => $tsOrder->vehicle_model,
                'vehicle_sub_model' => $tsOrder->vehicle_sub_model,
                'order_notes' => $tsOrder->order_notes,
                'tracking_number' => $tsOrder->tracking_number,
                'issue_date' => $tsOrder->created_at,
                'created_at' => $tsOrder->created_at,
                'updated_at' => $tsOrder->updated_at,
            ]);

            $this->importOrderItems($order, $tsOrder->id);
        } else {
            // In dry-run, still count items
            $this->countOrderItems($tsOrder->id);
        }

        $this->importedOrders++;
    }

    protected function importOrderItems(Order $order, int $tsOrderId): void
    {
        $items = DB::connection($this->sourceConnection)
            ->table('order_items')
            ->where('order_id', $tsOrderId)
            ->get();

        foreach ($items as $item) {
            $productSnapshot = $this->buildProductSnapshot($item);
            $variantSnapshot = $this->buildVariantSnapshot($item);

            $product = null;
            $variant = null;

            if ($item->product_id) {
                $product = Product::where('external_product_id', $item->product_id)
                    ->where('external_source', 'tunerstop')
                    ->first();
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::where('external_variant_id', $item->product_variant_id)
                    ->where('external_source', 'tunerstop')
                    ->first();
            }

            $parsedName = $this->parseProductName($item->name);

            // Check if addon exists in CRM (skip if not found)
            $addonId = null;
            if ($item->is_addon && $item->addon_id) {
                $addonExists = DB::table('addons')->where('id', $item->addon_id)->exists();
                $addonId = $addonExists ? $item->addon_id : null;
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'add_on_id' => $addonId,
                'sku' => $variant?->sku ?? ('TS-ITEM-' . $item->id),
                'product_name' => $item->name,
                'brand_name' => $parsedName['brand'] ?? $product?->brand?->name,
                'model_name' => $parsedName['model'] ?? $product?->model?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
                'discount' => $item->discount ?? 0,
                'line_total' => $item->price * $item->quantity - ($item->discount ?? 0),
                'tax_inclusive' => true,
                'product_snapshot' => $productSnapshot,
                'variant_snapshot' => $variantSnapshot,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            $this->importedItems++;
        }
    }

    protected function countOrderItems(int $tsOrderId): void
    {
        $itemCount = DB::connection($this->sourceConnection)
            ->table('order_items')
            ->where('order_id', $tsOrderId)
            ->count();

        $this->importedItems += $itemCount;
    }

    protected function buildProductSnapshot(object $item): array
    {
        $parsedName = $this->parseProductName($item->name);

        return [
            'external_product_id' => $item->product_id,
            'name' => $item->name,
            'brand_name' => $parsedName['brand'],
            'model_name' => $parsedName['model'],
            'finish_name' => $parsedName['finish'],
            'retail_price' => $item->price,
            'snapshot_date' => $item->created_at,
            'snapshot_version' => '1.0-historical',
            'source' => 'tunerstop_historical',
        ];
    }

    protected function buildVariantSnapshot(object $item): ?array
    {
        if (!$item->size && !$item->bolt_pattern && !$item->offset) {
            return null;
        }

        $sizeParts = $this->parseSize($item->size);

        return [
            'external_variant_id' => $item->product_variant_id,
            'size' => $item->size,
            'diameter' => $sizeParts['diameter'],
            'width' => $sizeParts['width'],
            'bolt_pattern' => $item->bolt_pattern,
            'offset' => $item->offset,
            'price' => $item->price,
            'cost' => null,
            'snapshot_date' => $item->created_at,
            'snapshot_version' => '1.0-historical',
            'source' => 'tunerstop_historical',
        ];
    }

    protected function parseProductName(string $name): array
    {
        $result = ['brand' => null, 'model' => null, 'finish' => null];
        $parts = explode(' - ', $name, 2);
        
        if (count($parts) >= 2) {
            $result['brand'] = trim($parts[0]);
            $secondPart = trim($parts[1]);
            
            $finishes = [
                'Matte Black', 'Satin Black', 'Gloss Black', 'Hyper Black', 'Diamond Black',
                'Matte Bronze', 'Satin Bronze', 'Bronze',
                'Platinum', 'Platinum Red', 'Silver', 'Diamond Silver',
                'Gold', 'Titanium', 'Chrome', 'Gunmetal',
                'White', 'Red', 'Blue', 'Matte Jet Black',
            ];

            foreach ($finishes as $finish) {
                if (str_contains($secondPart, $finish)) {
                    $result['finish'] = $finish;
                    $result['model'] = trim(str_replace($finish, '', $secondPart));
                    break;
                }
            }

            if (!$result['model']) {
                $result['model'] = $secondPart;
            }
        } else {
            $result['model'] = $name;
        }

        return $result;
    }

    protected function parseSize(?string $size): array
    {
        $result = ['diameter' => null, 'width' => null];
        if (!$size) return $result;

        if (preg_match('/^([\d.]+)x([\d.]+)/', $size, $matches)) {
            $result['width'] = $matches[1];
            $result['diameter'] = $matches[2];
        }

        return $result;
    }

    protected function getOrCreateDefaultRetailCustomer(): Customer
    {
        if ($this->dryRun) {
            return new Customer(['id' => 0]);
        }

        return Customer::firstOrCreate(
            ['email' => 'historical-tunerstop@retail.local'],
            [
                'first_name' => 'TunerStop',
                'last_name' => 'Historical Orders',
                'customer_type' => 'retail',
                'phone' => '0000000000',
                'is_active' => true,
                'notes' => 'Default customer for historical TunerStop orders imported for reporting.',
            ]
        );
    }

    protected function printSummary(): void
    {
        $this->info('');
        $this->info('=====================================');
        $this->info('📊 IMPORT SUMMARY');
        $this->info('=====================================');
        
        if ($this->dryRun) {
            $this->warn('🔍 DRY RUN - No data was saved');
        }
        
        $this->info("✅ Brands:     {$this->importedBrands}");
        $this->info("✅ Models:     {$this->importedModels}");
        $this->info("✅ Products:   {$this->importedProducts}");
        $this->info("✅ Variants:   {$this->importedVariants}");
        $this->info("✅ Customers:  {$this->importedCustomers}");
        $this->info("✅ Addresses:  {$this->importedAddresses}");
        $this->info("✅ Orders:     {$this->importedOrders}");
        $this->info("✅ Items:      {$this->importedItems}");
        
        if ($this->skippedOrders > 0) {
            $this->warn("⚠️ Skipped:   {$this->skippedOrders}");
        }

        if (!empty($this->errors)) {
            $this->warn('');
            $this->warn('⚠️ ERRORS (first 10):');
            foreach (array_slice($this->errors, 0, 10) as $error) {
                $this->warn("   - {$error}");
            }
        }

        $this->info('');
        $this->info('🎉 Import complete! Historical reports now available.');
    }
}
