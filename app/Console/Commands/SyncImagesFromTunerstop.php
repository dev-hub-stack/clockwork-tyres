<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SyncImagesFromTunerstop
 *
 * Reads product and variant image data from the tunerstop-admin production
 * database (tunerstop2020) and writes it into the local CRM database.
 *
 * Matching strategy:
 *   products         → CRM products.external_product_id  = tunerstop.products.id
 *   product_variants → CRM product_variants.external_variant_id = tunerstop.product_variants.id
 *
 * Usage:
 *   php artisan wholesale:sync-images-from-tunerstop
 *   php artisan wholesale:sync-images-from-tunerstop --only=products
 *   php artisan wholesale:sync-images-from-tunerstop --only=variants
 *   php artisan wholesale:sync-images-from-tunerstop --dry-run
 */
class SyncImagesFromTunerstop extends Command
{
    protected $signature = 'wholesale:sync-images-from-tunerstop
                            {--only=all : products|variants|all}
                            {--dry-run  : Preview counts without updating}
                            {--chunk=500 : Batch size for processing}';

    protected $description = 'Sync product and variant images from tunerstop-admin DB into CRM';

    public function handle(): int
    {
        $only   = $this->option('only');
        $dryRun = (bool) $this->option('dry-run');
        $chunk  = (int) $this->option('chunk');

        $this->info('Connecting to tunerstop-admin database...');

        try {
            DB::connection('tunerstop')->getPdo();
            $this->info('  Connection OK ✔');
        } catch (\Exception $e) {
            $this->error('Cannot connect to tunerstop DB: ' . $e->getMessage());
            return 1;
        }

        if ($only === 'all' || $only === 'products') {
            $this->syncProductImages($dryRun, $chunk);
        }

        if ($only === 'all' || $only === 'variants') {
            $this->syncVariantImages($dryRun, $chunk);
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry-run complete — no changes written.' : 'Image sync complete!');

        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Products → products.images  (stored as JSON array string)
    // ─────────────────────────────────────────────────────────────────────────
    protected function syncProductImages(bool $dryRun, int $chunk): void
    {
        $this->newLine();
        $this->info('=== Syncing products.images ===');

        $externalIds = DB::table('products')
            ->whereNotNull('external_product_id')
            ->pluck('external_product_id')
            ->toArray();

        $this->line('  CRM products with external_product_id: ' . count($externalIds));

        if (empty($externalIds)) {
            $this->warn('  No products with external_product_id — skipping.');
            return;
        }

        // Fetch all image data from tunerstop for the matched IDs
        $source = DB::connection('tunerstop')
            ->table('products')
            ->whereIn('id', $externalIds)
            ->whereNotNull('images')
            ->where('images', '!=', '')
            ->where('images', '!=', '[]')
            ->select('id', 'images')
            ->get();

        $this->line('  tunerstop products with images: ' . $source->count());

        if ($source->isEmpty()) {
            $this->warn('  No image data in source — skipping.');
            return;
        }

        if ($dryRun) {
            $this->line('  [dry-run] Would update ' . $source->count() . ' products.');
            $this->table(['tunerstop id', 'images preview'], $source->take(5)->map(fn($r) => [
                $r->id, substr($r->images, 0, 80)
            ]));
            return;
        }

        $updated = 0;
        foreach ($source as $row) {
            $n = DB::table('products')
                ->where('external_product_id', $row->id)
                ->update(['images' => $row->images]);
            $updated += $n;
        }

        $this->info("  ✔ Updated $updated product image records.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product variants → product_variants.image  (single path string)
    // ─────────────────────────────────────────────────────────────────────────
    protected function syncVariantImages(bool $dryRun, int $chunk): void
    {
        $this->newLine();
        $this->info('=== Syncing product_variants.image ===');

        $externalIds = DB::table('product_variants')
            ->whereNotNull('external_variant_id')
            ->pluck('external_variant_id')
            ->toArray();

        $this->line('  CRM variants with external_variant_id: ' . count($externalIds));

        if (empty($externalIds)) {
            $this->warn('  No variants with external_variant_id — skipping.');
            return;
        }

        // Confirm the source table has an 'image' column
        $cols = collect(DB::connection('tunerstop')->select('DESCRIBE product_variants'))
            ->pluck('Field')
            ->toArray();

        if (!in_array('image', $cols)) {
            $this->warn('  tunerstop product_variants has no "image" column — skipping.');
            return;
        }

        $total   = count($externalIds);
        $updated = 0;
        $found   = 0;
        $bar     = $this->output->createProgressBar((int) ceil($total / $chunk));
        $bar->start();

        foreach (array_chunk($externalIds, $chunk) as $batch) {
            $sourceRows = DB::connection('tunerstop')
                ->table('product_variants')
                ->whereIn('id', $batch)
                ->whereNotNull('image')
                ->where('image', '!=', '')
                ->select('id', 'image')
                ->get();

            $found += $sourceRows->count();

            if (!$dryRun) {
                foreach ($sourceRows as $row) {
                    $n = DB::table('product_variants')
                        ->where('external_variant_id', $row->id)
                        ->update(['image' => $row->image]);
                    $updated += $n;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->line("  [dry-run] Found $found variants with images in source.");
        } else {
            $this->info("  ✔ Updated $updated variant image records ($found with images in source).");
        }
    }
}
