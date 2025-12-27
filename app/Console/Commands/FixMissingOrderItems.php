<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

class FixMissingOrderItems extends Command
{
    protected $signature = 'fix:missing-order-items 
                            {--connection=tunerstop_source : Source database connection}
                            {--dry-run : Simulate without saving}';

    protected $description = 'Re-import order items for historical orders that have no items';

    protected string $sourceConnection;
    protected bool $dryRun;
    protected int $fixedOrders = 0;
    protected int $importedItems = 0;
    protected int $skippedOrders = 0;

    public function handle(): int
    {
        $this->sourceConnection = $this->option('connection');
        $this->dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('🔧 Fix Missing Order Items');
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

        // Find orders with no items
        $ordersWithoutItems = Order::where('external_source', 'tunerstop_historical')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_items')
                    ->whereRaw('order_items.order_id = orders.id');
            })
            ->get();

        $this->info("Found {$ordersWithoutItems->count()} orders without items");
        $this->info('');

        $bar = $this->output->createProgressBar($ordersWithoutItems->count());
        $bar->start();

        foreach ($ordersWithoutItems as $order) {
            $this->importOrderItems($order);
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
        $this->info("✅ Orders Fixed:     {$this->fixedOrders}");
        $this->info("✅ Items Imported:   {$this->importedItems}");
        $this->info("⚠️  Orders Skipped:  {$this->skippedOrders}");

        $this->info('');
        $this->info('🎉 Fix complete!');

        return Command::SUCCESS;
    }

    protected function importOrderItems(Order $order): void
    {
        // Get source order_id
        $sourceOrderId = $order->external_order_id;
        
        if (!$sourceOrderId) {
            $this->skippedOrders++;
            return;
        }

        // Get items from source (correct columns: quantity, not qty; no total column)
        $sourceItems = DB::connection($this->sourceConnection)
            ->table('order_items')
            ->where('order_id', $sourceOrderId)
            ->get();

        if ($sourceItems->isEmpty()) {
            $this->skippedOrders++;
            return;
        }

        foreach ($sourceItems as $item) {
            if (!$this->dryRun) {
                // Build product and variant snapshots
                $productSnapshot = $this->buildProductSnapshot($item);
                $variantSnapshot = $this->buildVariantSnapshot($item);

                // Calculate line total: (price * quantity) - discount
                $lineTotal = ($item->price * $item->quantity) - ($item->discount ?? 0);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => null,
                    'product_variant_id' => null,
                    'add_on_id' => null, // Don't reference old addon IDs - they don't exist in new system
                    'sku' => 'TS-ITEM-' . $item->id,
                    'product_name' => $item->name,
                    'brand_name' => $this->extractBrandFromName($item->name),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'line_total' => $lineTotal,
                    'discount' => $item->discount ?? 0,
                    'discount_type' => null,
                    'tax' => 0,
                    'notes' => $this->buildItemNotes($item, $item->is_addon),
                    'product_snapshot' => $productSnapshot,
                    'variant_snapshot' => $variantSnapshot,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);

                $this->importedItems++;
            } else {
                $this->importedItems++;
            }
        }

        $this->fixedOrders++;
    }

    protected function extractBrandFromName(?string $name): ?string
    {
        if (!$name) return null;
        
        $parts = explode(' - ', $name, 2);
        return trim($parts[0]) ?: null;
    }

    protected function buildProductSnapshot(object $item): ?array
    {
        return [
            'name' => $item->name,
            'price' => $item->price,
            'brand' => $this->extractBrandFromName($item->name),
        ];
    }

    protected function buildVariantSnapshot(object $item): ?array
    {
        return [
            'sku' => 'TS-ITEM-' . $item->id,
            'price' => $item->price,
            'options' => [
                'size' => $item->size ?? null,
                'bolt_pattern' => $item->bolt_pattern ?? null,
                'offset' => $item->offset ?? null,
                'type' => $item->type ?? null,
            ],
        ];
    }

    protected function buildItemNotes(object $item, bool $isAddon = false): ?string
    {
        $parts = [];
        if ($isAddon) $parts[] = "[Add-on]";
        if (!empty($item->size)) $parts[] = "Size: {$item->size}";
        if (!empty($item->bolt_pattern)) $parts[] = "Bolt Pattern: {$item->bolt_pattern}";
        if (!empty($item->offset)) $parts[] = "Offset: {$item->offset}";
        if (!empty($item->type)) $parts[] = "Type: {$item->type}";
        
        return empty($parts) ? null : implode(', ', $parts);
    }
}
