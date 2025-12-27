<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Catalog\Models\Product;

class LinkHistoricalProductsToInventory extends Command
{
    protected $signature = 'link:historical-products-to-inventory 
                            {--dry-run : Show what would be updated without saving}';

    protected $description = 'Link historical order items to real products in inventory using smart matching';

    protected int $matched = 0;
    protected int $skipped = 0;
    protected array $matchingLog = [];

    public function handle(): int
    {
        $this->info('');
        $this->info('🔗 Link Historical Products to Inventory');
        $this->info('=====================================');
        
        if ($this->option('dry-run')) {
            $this->info('🔍 DRY RUN MODE - No data will be saved');
        }
        $this->info('');

        // Get all unlinked historical order items
        $items = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.external_source', 'tunerstop_historical')
            ->whereNull('order_items.product_id')
            ->select('order_items.id', 'order_items.product_name', 'order_items.brand_name')
            ->distinct()
            ->get();

        $total = $items->count();
        $this->info("Found {$total} distinct unlinked products");
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($items as $item) {
            $this->tryLinkProduct($item);
            $bar->advance();
        }

        $bar->finish();
        $this->info('');
        $this->info('');

        // Summary
        $this->info('=====================================');
        $this->info('📊 LINKING SUMMARY');
        $this->info('=====================================');
        if ($this->option('dry-run')) {
            $this->info('🔍 DRY RUN - No data was saved');
        }
        $this->info("✅ Products Linked:   {$this->matched}");
        $this->info("⚠️  Products Skipped:  {$this->skipped}");
        $this->info('');
        
        if (!empty($this->matchingLog)) {
            $this->info('📝 Matching Details (top 20):');
            foreach (array_slice($this->matchingLog, 0, 20) as $log) {
                $this->info("   {$log}");
            }
        }
        
        $this->info('');
        $this->info('🎉 Linking complete!');

        return Command::SUCCESS;
    }

    protected function tryLinkProduct(object $item): void
    {
        // Try different matching strategies in order
        
        // Strategy 1: Exact brand + normalized model match
        $product = $this->matchByNormalizedName($item);
        
        // Strategy 2: Fuzzy brand + model match
        if (!$product) {
            $product = $this->matchByFuzzyBrand($item);
        }
        
        // Strategy 3: Just model match (risky, only if model is distinctive)
        if (!$product) {
            $product = $this->matchByDistinctiveModel($item);
        }

        if ($product) {
            $this->applyMatch($item, $product);
        } else {
            $this->skipped++;
        }
    }

    protected function matchByNormalizedName(object $item): ?object
    {
        if (!$item->product_name || !str_contains($item->product_name, ' - ')) {
            return null;
        }

        $parts = explode(' - ', $item->product_name, 2);
        $brandName = trim($parts[0]);
        $modelFinish = trim($parts[1]);
        
        // Extract model (first word/hyphenated segment)
        $modelName = $this->extractModelName($modelFinish);
        if (!$modelName) return null;

        // Normalize model name: remove hyphens, extra spaces
        $normalizedModel = $this->normalizeModelName($modelName);

        // Find brand (case-insensitive, close match)
        $brand = DB::table('brands')
            ->whereRaw("LOWER(name) LIKE LOWER(?)", ["%{$brandName}%"])
            ->first();
        
        if (!$brand) return null;

        // Find product with normalized model name
        $product = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where(function ($q) use ($normalizedModel, $modelName) {
                $q->whereRaw("REPLACE(LOWER(name), '-', '') = ?", [$normalizedModel])
                  ->orWhereRaw("LOWER(name) = ?", [strtolower($modelName)]);
            })
            ->first();

        if ($product) {
            $this->matchingLog[] = "✅ '{$item->product_name}' → Product ID {$product->id} (normalized match)";
        }

        return $product;
    }

    protected function matchByFuzzyBrand(object $item): ?object
    {
        if (!$item->product_name || !str_contains($item->product_name, ' - ')) {
            return null;
        }

        $parts = explode(' - ', $item->product_name, 2);
        $brandName = trim($parts[0]);
        $modelFinish = trim($parts[1]);
        
        $modelName = $this->extractModelName($modelFinish);
        if (!$modelName) return null;

        $normalizedModel = $this->normalizeModelName($modelName);

        // Get all brands and do fuzzy matching
        $brands = DB::table('brands')->get(['id', 'name']);
        $bestBrand = null;
        $bestScore = 0;

        foreach ($brands as $b) {
            $score = $this->calculateSimilarity($brandName, $b->name);
            if ($score > $bestScore && $score > 0.6) {  // 60% similarity threshold
                $bestScore = $score;
                $bestBrand = $b;
            }
        }

        if (!$bestBrand) return null;

        // Find product with normalized model
        $product = DB::table('products')
            ->where('brand_id', $bestBrand->id)
            ->where(function ($q) use ($normalizedModel, $modelName) {
                $q->whereRaw("REPLACE(LOWER(name), '-', '') = ?", [$normalizedModel])
                  ->orWhereRaw("LOWER(name) LIKE ?", ["%{$modelName}%"]);
            })
            ->first();

        if ($product) {
            $this->matchingLog[] = "✅ '{$item->product_name}' → Product ID {$product->id} (fuzzy match: {$bestBrand->name})";
        }

        return $product;
    }

    protected function matchByDistinctiveModel(object $item): ?object
    {
        // Only try this for models with 4+ characters or distinctive patterns
        if (!$item->product_name || !str_contains($item->product_name, ' - ')) {
            return null;
        }

        $parts = explode(' - ', $item->product_name, 2);
        $modelFinish = trim($parts[1]);
        $modelName = $this->extractModelName($modelFinish);
        
        if (!$modelName || strlen($modelName) < 3) {
            return null;
        }

        // Check if model has distinctive patterns (numbers, specific brands)
        if (!preg_match('/[A-Z]{2,}[0-9]{3,}|HF[0-9]|MR[0-9]|SR[0-9]/', $modelName)) {
            return null;
        }

        $normalizedModel = $this->normalizeModelName($modelName);

        // Search across all products by normalized model
        $product = DB::table('products')
            ->where(function ($q) use ($normalizedModel) {
                $q->whereRaw("REPLACE(LOWER(name), '-', '') = ?", [$normalizedModel])
                  ->orWhereRaw("REPLACE(LOWER(name), '-', '') LIKE ?", ["%{$normalizedModel}%"]);
            })
            ->first();

        if ($product) {
            $this->matchingLog[] = "⚠️  '{$item->product_name}' → Product ID {$product->id} (model-only match)";
        }

        return $product;
    }

    protected function extractModelName(?string $text): ?string
    {
        if (!$text) return null;
        
        // Split by space and get first part (model name)
        $parts = explode(' ', trim($text));
        return $parts[0] ?: null;
    }

    protected function normalizeModelName(string $model): string
    {
        // Remove hyphens and normalize for comparison
        return strtolower(str_replace('-', '', $model));
    }

    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        // Exact match
        if ($str1 === $str2) return 1.0;
        
        // One contains the other
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) return 0.9;
        
        // Levenshtein distance similarity
        $len = max(strlen($str1), strlen($str2));
        if ($len === 0) return 1.0;
        
        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $len);
    }

    protected function applyMatch(object $item, object $product): void
    {
        if (!$this->option('dry-run')) {
            // Update all order items with this product_name to link them
            OrderItem::whereNull('product_id')
                ->where('product_name', $item->product_name)
                ->update([
                    'product_id' => $product->id,
                ]);
        }

        $this->matched++;
    }
}
