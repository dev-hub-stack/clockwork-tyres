<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateProductNoiseReport extends Command
{
    protected $signature = 'report:product-noise
                            {--output=docs/product-noise-data-report.md : Workspace-relative output path for the markdown report}';

    protected $description = 'Generate a reusable cleanup report for brands without products and products without variants.';

    public function handle(): int
    {
        $generatedAt = CarbonImmutable::now();

        $brandsWithoutProducts = DB::table('brands as b')
            ->leftJoin('products as p', 'p.brand_id', '=', 'b.id')
            ->leftJoin('order_items as oi', 'oi.brand_name', '=', 'b.name')
            ->whereNull('b.deleted_at')
            ->groupBy('b.id', 'b.name', 'b.status', 'b.external_source', 'b.external_id')
            ->havingRaw('COUNT(DISTINCT p.id) = 0')
            ->orderBy('b.name')
            ->get([
                'b.id',
                'b.name',
                'b.status',
                'b.external_source',
                'b.external_id',
                DB::raw('COUNT(DISTINCT oi.id) as order_item_refs'),
            ]);

        $productsWithoutVariants = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_variants as pv', 'pv.product_id', '=', 'p.id')
            ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
            ->leftJoin('product_inventories as pi', 'pi.product_id', '=', 'p.id')
            ->groupBy(
                'p.id',
                'p.name',
                'p.sku',
                'p.status',
                'p.brand_id',
                'b.name',
                'p.external_source',
                'p.external_product_id'
            )
            ->havingRaw('COUNT(DISTINCT pv.id) = 0')
            ->orderBy('b.name')
            ->orderBy('p.name')
            ->get([
                'p.id',
                'p.name',
                'p.sku',
                'p.status',
                'p.brand_id',
                DB::raw("COALESCE(b.name, 'No Brand') as brand_name"),
                'p.external_source',
                'p.external_product_id',
                DB::raw('COUNT(DISTINCT oi.id) as order_item_refs'),
                DB::raw('COUNT(DISTINCT pi.id) as inventory_records'),
                DB::raw('COALESCE(SUM(DISTINCT pi.quantity), 0) as inventory_qty_signal'),
            ]);

        $productsWithoutVariantsUsedInOrders = $productsWithoutVariants
            ->where('order_item_refs', '>', 0)
            ->values();

        $productsWithoutVariantsWithInventoryLinks = $productsWithoutVariants
            ->filter(fn ($product) => (int) $product->inventory_records > 0)
            ->values();

        $productsWithoutVariantsZeroUsage = $productsWithoutVariants
            ->filter(fn ($product) => (int) $product->order_item_refs === 0 && (int) $product->inventory_records === 0)
            ->values();

        $productsWithoutVariantsByBrand = $productsWithoutVariants
            ->groupBy('brand_name')
            ->map(fn (Collection $group, string $brandName) => [
                'brand_name' => $brandName,
                'product_count' => $group->count(),
                'used_in_orders_count' => $group->filter(fn ($product) => (int) $product->order_item_refs > 0)->count(),
                'inventory_linked_count' => $group->filter(fn ($product) => (int) $product->inventory_records > 0)->count(),
            ])
            ->sortByDesc('product_count')
            ->values();

        $outputPath = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $this->renderMarkdownReport(
            $generatedAt,
            $brandsWithoutProducts,
            $productsWithoutVariantsByBrand,
            $productsWithoutVariantsUsedInOrders,
            $productsWithoutVariantsWithInventoryLinks,
            $productsWithoutVariantsZeroUsage,
        ));

        $this->info('Product noise report generated successfully.');
        $this->line('Output: ' . $outputPath);
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Brands with no products', $brandsWithoutProducts->count()],
            ['Products with no variants', $productsWithoutVariants->count()],
            ['Products with no variants and no order refs or inventory links', $productsWithoutVariantsZeroUsage->count()],
            ['Products with no variants but used in orders', $productsWithoutVariantsUsedInOrders->count()],
            ['Products with no variants but linked to inventory', $productsWithoutVariantsWithInventoryLinks->count()],
        ]);

        return self::SUCCESS;
    }

    private function renderMarkdownReport(
        CarbonImmutable $generatedAt,
        Collection $brandsWithoutProducts,
        Collection $productsWithoutVariantsByBrand,
        Collection $productsWithoutVariantsUsedInOrders,
        Collection $productsWithoutVariantsWithInventoryLinks,
        Collection $productsWithoutVariantsZeroUsage,
    ): string {
        $productsWithoutVariantsCount = (int) $productsWithoutVariantsByBrand->sum('product_count');

        $lines = [
            '# Product Noise Data Report',
            '',
            'Generated from the current `reporting-crm` database on ' . $generatedAt->format('Y-m-d H:i:s T') . '.',
            '',
            '## Summary',
            '',
            '- Brands with no products: ' . $brandsWithoutProducts->count(),
            '- Products with no variants: ' . $productsWithoutVariantsCount,
            '- Products with no variants and no detected order references: ' . ($productsWithoutVariantsCount - $productsWithoutVariantsUsedInOrders->count()),
            '- Products with no variants and no order refs or inventory links: ' . $productsWithoutVariantsZeroUsage->count(),
            '- Products with no variants but used in orders: ' . $productsWithoutVariantsUsedInOrders->count(),
            '- Products with no variants but linked to inventory: ' . $productsWithoutVariantsWithInventoryLinks->count(),
            '',
            '## Main Findings',
            '',
            '### 1. No orphan brands',
            '',
            $brandsWithoutProducts->isEmpty()
                ? 'There are currently no non-deleted brands that have zero products.'
                : 'There are non-deleted brands with zero products. Review the table below before cleanup.',
            '',
            '### 2. Large variantless product bucket',
            '',
            'The main data-quality issue is products that exist without any `product_variants` rows.',
            '',
            'Top brands for variantless products:',
            '',
            '| Brand | Count | Used in Orders | Inventory Linked |',
            '| --- | ---: | ---: | ---: |',
        ];

        foreach ($productsWithoutVariantsByBrand->take(15) as $brandSummary) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d |',
                $this->escapeTable((string) $brandSummary['brand_name']),
                $brandSummary['product_count'],
                $brandSummary['used_in_orders_count'],
                $brandSummary['inventory_linked_count'],
            );
        }

        $lines = array_merge($lines, [
            '',
            '## Do Not Bulk Delete First',
            '',
            'These products have no variants but are still referenced by order items:',
            '',
            '| Product ID | Name | Brand | Order Item Refs |',
            '| --- | --- | --- | ---: |',
        ]);

        if ($productsWithoutVariantsUsedInOrders->isEmpty()) {
            $lines[] = '| None | - | - | 0 |';
        } else {
            foreach ($productsWithoutVariantsUsedInOrders as $product) {
                $lines[] = sprintf(
                    '| %d | %s | %s | %d |',
                    $product->id,
                    $this->escapeTable((string) $product->name),
                    $this->escapeTable((string) $product->brand_name),
                    (int) $product->order_item_refs,
                );
            }
        }

        $lines = array_merge($lines, [
            '',
            'These products have no variants but are still linked to inventory:',
            '',
            '| Product ID | Name | Brand | Inventory Records | Inventory Qty Signal |',
            '| --- | --- | --- | ---: | ---: |',
        ]);

        if ($productsWithoutVariantsWithInventoryLinks->isEmpty()) {
            $lines[] = '| None | - | - | 0 | 0 |';
        } else {
            foreach ($productsWithoutVariantsWithInventoryLinks as $product) {
                $lines[] = sprintf(
                    '| %d | %s | %s | %d | %s |',
                    $product->id,
                    $this->escapeTable((string) $product->name),
                    $this->escapeTable((string) $product->brand_name),
                    (int) $product->inventory_records,
                    $this->escapeTable((string) $product->inventory_qty_signal),
                );
            }
        }

        $lines = array_merge($lines, [
            '',
            '## Likely Safe Cleanup Bucket',
            '',
            'There are ' . $productsWithoutVariantsZeroUsage->count() . ' products that:',
            '',
            '- have no variants',
            '- have no order item references',
            '- have no inventory links',
            '',
            'Representative examples from that bucket:',
            '',
            '| Product ID | Name | Brand | External Product ID |',
            '| --- | --- | --- | --- |',
        ]);

        foreach ($productsWithoutVariantsZeroUsage->take(10) as $product) {
            $lines[] = sprintf(
                '| %d | %s | %s | %s |',
                $product->id,
                $this->escapeTable((string) $product->name),
                $this->escapeTable((string) $product->brand_name),
                $this->escapeTable((string) $product->external_product_id),
            );
        }

        $lines = array_merge($lines, [
            '',
            '## Recommended Cleanup Order',
            '',
            '1. Review and likely remove the ' . $productsWithoutVariantsZeroUsage->count() . ' zero-usage variantless products first.',
            '2. Handle the ' . $productsWithoutVariantsWithInventoryLinks->count() . ' inventory-linked products separately so inventory records are resolved before deletion.',
            '3. Handle the ' . $productsWithoutVariantsUsedInOrders->count() . ' order-linked products separately so historical order integrity is preserved.',
            '4. After cleanup, backfill or enforce variant creation during imports to stop the same pattern from returning.',
            '',
        ]);

        return implode(PHP_EOL, $lines);
    }

    private function escapeTable(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}