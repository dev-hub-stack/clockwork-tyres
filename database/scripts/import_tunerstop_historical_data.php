<?php

/**
 * TunerStop Historical Data Import Script
 * 
 * This script imports historical order data from TunerStop database dump (tunerstop-data.sql)
 * into the Reporting CRM system for generating comprehensive reports.
 * 
 * DATA PERIOD: October 2020 - December 2025 (5+ years)
 * 
 * Usage: php artisan tinker < database/scripts/import_tunerstop_historical_data.php
 * Or:    php database/scripts/import_tunerstop_historical_data.php
 * 
 * @author CRM Development Team
 * @date December 2024
 */

namespace Database\Scripts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\Model as ProductModel;
use App\Modules\Products\Models\Finish;
use App\Modules\Customers\Models\Customer;
use Carbon\Carbon;

class TunerstopHistoricalDataImporter
{
    /**
     * TunerStop status mapping to CRM statuses
     * TunerStop: -1=Not Fulfilled, 0=pending, 1=completed, 2=rejected
     */
    protected array $statusMap = [
        -1 => OrderStatus::PENDING,
        0 => OrderStatus::PENDING,
        1 => OrderStatus::COMPLETED,
        2 => OrderStatus::CANCELLED,
    ];

    protected int $batchSize = 500;
    protected int $importedOrders = 0;
    protected int $importedItems = 0;
    protected int $importedBrands = 0;
    protected int $importedModels = 0;
    protected int $skippedOrders = 0;
    protected array $errors = [];

    /**
     * Source database connection name (configure in config/database.php)
     */
    protected string $sourceConnection = 'tunerstop_source';

    /**
     * Run the import
     */
    public function import(): void
    {
        $this->info("🚀 Starting TunerStop Historical Data Import...");
        $this->info("================================================");

        try {
            DB::beginTransaction();

            // Step 1: Import reference data (brands, models, finishes)
            $this->importBrands();
            $this->importModels();
            $this->importFinishes();

            // Step 2: Import products and variants (needed for snapshots)
            $this->importProducts();
            $this->importProductVariants();

            // Step 3: Import orders with items
            $this->importOrders();

            DB::commit();

            $this->printSummary();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Import failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Import brands from TunerStop
     */
    protected function importBrands(): void
    {
        $this->info("\n📦 Importing Brands...");

        $tunerstopBrands = DB::connection($this->sourceConnection)
            ->table('brands')
            ->select('id', 'name', 'image', 'created_at', 'updated_at')
            ->get();

        foreach ($tunerstopBrands as $tsBrand) {
            Brand::updateOrCreate(
                ['external_id' => $tsBrand->id, 'external_source' => 'tunerstop'],
                [
                    'name' => $tsBrand->name,
                    'logo_url' => $tsBrand->image,
                    'is_active' => true,
                    'created_at' => $tsBrand->created_at,
                    'updated_at' => $tsBrand->updated_at,
                ]
            );
            $this->importedBrands++;
        }

        $this->info("   ✅ Imported {$this->importedBrands} brands");
    }

    /**
     * Import models from TunerStop
     */
    protected function importModels(): void
    {
        $this->info("\n📦 Importing Models...");

        $tunerstopModels = DB::connection($this->sourceConnection)
            ->table('models')
            ->select('id', 'name', 'created_at', 'updated_at')
            ->get();

        foreach ($tunerstopModels as $tsModel) {
            ProductModel::updateOrCreate(
                ['external_id' => $tsModel->id, 'external_source' => 'tunerstop'],
                [
                    'name' => $tsModel->name,
                    'is_active' => true,
                    'created_at' => $tsModel->created_at,
                    'updated_at' => $tsModel->updated_at,
                ]
            );
            $this->importedModels++;
        }

        $this->info("   ✅ Imported {$this->importedModels} models");
    }

    /**
     * Import finishes from TunerStop
     */
    protected function importFinishes(): void
    {
        $this->info("\n📦 Importing Finishes...");

        $count = 0;
        $tunerstopFinishes = DB::connection($this->sourceConnection)
            ->table('finishes')
            ->select('id', 'finish', 'created_at', 'updated_at')
            ->get();

        foreach ($tunerstopFinishes as $tsFinish) {
            Finish::updateOrCreate(
                ['external_id' => $tsFinish->id, 'external_source' => 'tunerstop'],
                [
                    'name' => $tsFinish->finish,
                    'is_active' => true,
                    'created_at' => $tsFinish->created_at,
                    'updated_at' => $tsFinish->updated_at,
                ]
            );
            $count++;
        }

        $this->info("   ✅ Imported {$count} finishes");
    }

    /**
     * Import products from TunerStop
     */
    protected function importProducts(): void
    {
        $this->info("\n📦 Importing Products...");

        $count = 0;
        DB::connection($this->sourceConnection)
            ->table('products')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($products) use (&$count) {
                foreach ($products as $tsProduct) {
                    // Find mapped brand/model
                    $brand = Brand::where('external_id', $tsProduct->brand_id)
                        ->where('external_source', 'tunerstop')
                        ->first();
                    
                    $model = ProductModel::where('external_id', $tsProduct->model_id)
                        ->where('external_source', 'tunerstop')
                        ->first();

                    $finish = Finish::where('external_id', $tsProduct->finish_id)
                        ->where('external_source', 'tunerstop')
                        ->first();

                    Product::updateOrCreate(
                        ['external_id' => $tsProduct->id, 'external_source' => 'tunerstop'],
                        [
                            'name' => $tsProduct->name,
                            'sku' => 'TS-P-' . $tsProduct->id,
                            'description' => $tsProduct->product_full_name,
                            'brand_id' => $brand?->id,
                            'model_id' => $model?->id,
                            'finish_id' => $finish?->id,
                            'retail_price' => $tsProduct->price ?? 0,
                            'is_active' => $tsProduct->status == 1,
                            'created_at' => $tsProduct->created_at,
                            'updated_at' => $tsProduct->updated_at,
                        ]
                    );
                    $count++;
                }
                $this->info("   ... processed {$count} products");
            });

        $this->info("   ✅ Imported {$count} products");
    }

    /**
     * Import product variants from TunerStop
     */
    protected function importProductVariants(): void
    {
        $this->info("\n📦 Importing Product Variants...");

        $count = 0;
        DB::connection($this->sourceConnection)
            ->table('product_variants')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($this->batchSize, function ($variants) use (&$count) {
                foreach ($variants as $tsVariant) {
                    // Find mapped product
                    $product = Product::where('external_id', $tsVariant->product_id)
                        ->where('external_source', 'tunerstop')
                        ->first();

                    if (!$product) continue;

                    ProductVariant::updateOrCreate(
                        ['external_id' => $tsVariant->id, 'external_source' => 'tunerstop'],
                        [
                            'product_id' => $product->id,
                            'sku' => $tsVariant->sku,
                            'size' => $tsVariant->size,
                            'diameter' => $tsVariant->rim_diameter,
                            'width' => $tsVariant->rim_width,
                            'bolt_pattern' => $tsVariant->bolt_pattern,
                            'offset' => $tsVariant->offset,
                            'hub_bore' => $tsVariant->hub_bore,
                            'weight' => $tsVariant->weight,
                            'cost' => is_numeric($tsVariant->cost) ? $tsVariant->cost : null,
                            'price' => $tsVariant->uae_retail_price ?? 0,
                            'is_active' => true,
                            'created_at' => $tsVariant->created_at,
                            'updated_at' => $tsVariant->updated_at,
                        ]
                    );
                    $count++;
                }
                
                if ($count % 5000 == 0) {
                    $this->info("   ... processed {$count} variants");
                }
            });

        $this->info("   ✅ Imported {$count} product variants");
    }

    /**
     * Import orders from TunerStop
     */
    protected function importOrders(): void
    {
        $this->info("\n📦 Importing Orders...");

        // Get or create default retail customer for historical orders
        $defaultCustomer = $this->getOrCreateDefaultRetailCustomer();

        DB::connection($this->sourceConnection)
            ->table('orders')
            ->where('status', '>=', 0) // Only import non-negative status (exclude cart abandonments)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($orders) use ($defaultCustomer) {
                foreach ($orders as $tsOrder) {
                    try {
                        $this->importSingleOrder($tsOrder, $defaultCustomer);
                    } catch (\Exception $e) {
                        $this->errors[] = "Order {$tsOrder->id}: " . $e->getMessage();
                        $this->skippedOrders++;
                    }
                }
                $this->info("   ... processed {$this->importedOrders} orders");
            });

        $this->info("   ✅ Imported {$this->importedOrders} orders with {$this->importedItems} items");
        
        if ($this->skippedOrders > 0) {
            $this->warn("   ⚠️ Skipped {$this->skippedOrders} orders due to errors");
        }
    }

    /**
     * Import a single order with its items
     */
    protected function importSingleOrder(object $tsOrder, Customer $defaultCustomer): void
    {
        // Check if already imported
        $existingOrder = Order::where('external_order_id', $tsOrder->id)
            ->where('external_source', 'tunerstop_historical')
            ->first();

        if ($existingOrder) {
            return; // Skip already imported
        }

        // Map status
        $orderStatus = $this->statusMap[$tsOrder->status] ?? OrderStatus::PENDING;
        
        // Auto-complete old orders (before Nov 2025) unless cancelled
        // This handles the requirement to mark fulfillment as completed for historical orders until Oct 2025
        if ($orderStatus !== OrderStatus::CANCELLED) {
            $orderDate = Carbon::parse($tsOrder->created_at);
            if ($orderDate->lt('2025-11-01')) {
                $orderStatus = OrderStatus::COMPLETED;
            }
        }
        
        // Determine payment status based on paid_amount
        $paymentStatus = PaymentStatus::UNPAID;
        if ($tsOrder->paid_amount >= $tsOrder->total) {
            $paymentStatus = PaymentStatus::PAID;
        } elseif ($tsOrder->paid_amount > 0) {
            $paymentStatus = PaymentStatus::PARTIAL;
        }

        // Create order
        $order = Order::create([
            'document_type' => DocumentType::INVOICE,
            'order_number' => 'TS-' . $tsOrder->order_number,
            'external_order_id' => $tsOrder->id,
            'external_source' => 'tunerstop_historical',
            'customer_id' => $defaultCustomer->id,
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

        $this->importedOrders++;

        // Import order items
        $this->importOrderItems($order, $tsOrder->id);
    }

    /**
     * Import order items for an order
     */
    protected function importOrderItems(Order $order, int $tsOrderId): void
    {
        $tsItems = DB::connection($this->sourceConnection)
            ->table('order_items')
            ->where('order_id', $tsOrderId)
            ->get();

        foreach ($tsItems as $tsItem) {
            // Build product snapshot from available data
            $productSnapshot = $this->buildProductSnapshot($tsItem);
            $variantSnapshot = $this->buildVariantSnapshot($tsItem);

            // Try to find mapped product/variant in CRM
            $product = null;
            $variant = null;

            if ($tsItem->product_id) {
                $product = Product::where('external_id', $tsItem->product_id)
                    ->where('external_source', 'tunerstop')
                    ->first();
            }

            if ($tsItem->product_variant_id) {
                $variant = ProductVariant::where('external_id', $tsItem->product_variant_id)
                    ->where('external_source', 'tunerstop')
                    ->first();
            }

            // Extract brand/model from name if possible (format: "Brand - Model Finish")
            $parsedName = $this->parseProductName($tsItem->name);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'add_on_id' => $tsItem->is_addon ? $tsItem->addon_id : null,
                'sku' => $variant?->sku ?? ('TS-ITEM-' . $tsItem->id),
                'product_name' => $tsItem->name,
                'brand_name' => $parsedName['brand'] ?? $product?->brand?->name,
                'model_name' => $parsedName['model'] ?? $product?->model?->name,
                'quantity' => $tsItem->quantity,
                'unit_price' => $tsItem->price,
                'discount' => $tsItem->discount ?? 0,
                'line_total' => $tsItem->price * $tsItem->quantity - ($tsItem->discount ?? 0),
                'tax_inclusive' => true,
                'product_snapshot' => $productSnapshot,
                'variant_snapshot' => $variantSnapshot,
                'created_at' => $tsItem->created_at,
                'updated_at' => $tsItem->updated_at,
            ]);

            $this->importedItems++;
        }
    }

    /**
     * Build product snapshot from TunerStop order item
     */
    protected function buildProductSnapshot(object $tsItem): array
    {
        $parsedName = $this->parseProductName($tsItem->name);

        return [
            'external_product_id' => $tsItem->product_id,
            'name' => $tsItem->name,
            'brand_name' => $parsedName['brand'],
            'model_name' => $parsedName['model'],
            'finish_name' => $parsedName['finish'],
            'retail_price' => $tsItem->price,
            'snapshot_date' => $tsItem->created_at,
            'snapshot_version' => '1.0-historical',
            'source' => 'tunerstop_historical',
        ];
    }

    /**
     * Build variant snapshot from TunerStop order item
     */
    protected function buildVariantSnapshot(object $tsItem): ?array
    {
        if (!$tsItem->size && !$tsItem->bolt_pattern && !$tsItem->offset) {
            return null;
        }

        // Parse size (format: "8.5x17" or "8.5x17" or similar)
        $sizeParts = $this->parseSize($tsItem->size);

        return [
            'external_variant_id' => $tsItem->product_variant_id,
            'size' => $tsItem->size,
            'diameter' => $sizeParts['diameter'],
            'width' => $sizeParts['width'],
            'bolt_pattern' => $tsItem->bolt_pattern,
            'offset' => $tsItem->offset,
            'price' => $tsItem->price,
            'cost' => null, // Cost not available in order_items, would need variant lookup
            'snapshot_date' => $tsItem->created_at,
            'snapshot_version' => '1.0-historical',
            'source' => 'tunerstop_historical',
        ];
    }

    /**
     * Parse product name to extract brand, model, finish
     * Format examples:
     * - "JR Wheels - JR30 Platinum Red"
     * - "BLACK RHINO - CHAMBER Matte Black"
     * - "Stealth Custom Series - K5 Matte Jet Black"
     */
    protected function parseProductName(string $name): array
    {
        $result = [
            'brand' => null,
            'model' => null,
            'finish' => null,
        ];

        // Try to split by " - "
        $parts = explode(' - ', $name, 2);
        
        if (count($parts) >= 2) {
            $result['brand'] = trim($parts[0]);
            
            // Second part contains model and finish
            // Try to extract model (usually first word(s) before finish)
            $secondPart = trim($parts[1]);
            
            // Common finishes to look for
            $finishes = [
                'Matte Black', 'Satin Black', 'Gloss Black', 'Hyper Black', 'Diamond Black',
                'Matte Bronze', 'Satin Bronze', 'Bronze',
                'Platinum', 'Platinum Red', 'Silver', 'Diamond Silver',
                'Gold', 'Titanium', 'Chrome', 'Gunmetal',
                'White', 'Red', 'Blue',
            ];

            foreach ($finishes as $finish) {
                if (str_contains($secondPart, $finish)) {
                    $result['finish'] = $finish;
                    $result['model'] = trim(str_replace($finish, '', $secondPart));
                    break;
                }
            }

            // If no finish found, assume whole second part is model
            if (!$result['model']) {
                $result['model'] = $secondPart;
            }
        } else {
            // No separator found, use whole name
            $result['model'] = $name;
        }

        return $result;
    }

    /**
     * Parse size string to extract diameter and width
     * Format: "8.5x17", "9x20", etc.
     */
    protected function parseSize(?string $size): array
    {
        $result = ['diameter' => null, 'width' => null];

        if (!$size) return $result;

        // Match pattern like "8.5x17" or "9x20"
        if (preg_match('/^([\d.]+)x([\d.]+)/', $size, $matches)) {
            $result['width'] = $matches[1];
            $result['diameter'] = $matches[2];
        }

        return $result;
    }

    /**
     * Get or create default retail customer for historical imports
     */
    protected function getOrCreateDefaultRetailCustomer(): Customer
    {
        return Customer::firstOrCreate(
            ['email' => 'historical-tunerstop@retail.local'],
            [
                'first_name' => 'TunerStop',
                'last_name' => 'Historical Orders',
                'customer_type' => 'retail',
                'phone' => '0000000000',
                'is_active' => true,
                'notes' => 'Default customer for historical TunerStop orders imported for reporting purposes.',
            ]
        );
    }

    /**
     * Print import summary
     */
    protected function printSummary(): void
    {
        $this->info("\n================================================");
        $this->info("📊 IMPORT SUMMARY");
        $this->info("================================================");
        $this->info("✅ Brands imported:   {$this->importedBrands}");
        $this->info("✅ Models imported:   {$this->importedModels}");
        $this->info("✅ Orders imported:   {$this->importedOrders}");
        $this->info("✅ Order items:       {$this->importedItems}");
        
        if ($this->skippedOrders > 0) {
            $this->warn("⚠️ Orders skipped:    {$this->skippedOrders}");
        }

        if (!empty($this->errors)) {
            $this->warn("\n⚠️ ERRORS:");
            foreach (array_slice($this->errors, 0, 10) as $error) {
                $this->warn("   - {$error}");
            }
            if (count($this->errors) > 10) {
                $this->warn("   ... and " . (count($this->errors) - 10) . " more errors");
            }
        }

        $this->info("\n🎉 Historical data import completed!");
        $this->info("   Reports can now be generated from October 2020 onwards.");
    }

    /**
     * Console output helpers
     */
    protected function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    protected function warn(string $message): void
    {
        echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
    }

    protected function error(string $message): void
    {
        echo "\033[31m" . $message . "\033[0m" . PHP_EOL;
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && realpath($argv[0]) === realpath(__FILE__)) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $app = require_once __DIR__ . '/../../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    $importer = new TunerstopHistoricalDataImporter();
    $importer->import();
}
