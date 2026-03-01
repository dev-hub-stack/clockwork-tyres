<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBrandImagesFromTunerstop extends Command
{
    protected $signature = 'wholesale:sync-brand-images
                            {--dry-run : Preview changes without updating}';

    protected $description = 'Sync brand logo images from tunerstop DB into the CRM brands table.
                              Matches brands by external_id (CRM brands.external_id = tunerstop brands.id).';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        $this->info($isDry ? '[DRY RUN] Previewing brand image sync...' : 'Syncing brand images from tunerstop...');

        // Load all tunerstop brands that have an image
        $source = DB::connection('tunerstop')
            ->table('brands')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->get(['id', 'name', 'image'])
            ->keyBy('id');

        $this->info("Found {$source->count()} tunerstop brands with images.");

        // Load CRM brands that have an external_id
        $crmBrands = DB::table('brands')
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->get(['id', 'name', 'logo', 'external_id']);

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($crmBrands as $brand) {
            $src = $source->get((int) $brand->external_id);

            if (!$src) {
                $notFound++;
                continue;
            }

            if ($brand->logo === $src->image) {
                $skipped++;
                continue;
            }

            $this->line("  [{$brand->name}] {$brand->logo} → {$src->image}");

            if (!$isDry) {
                DB::table('brands')
                    ->where('id', $brand->id)
                    ->update(['logo' => $src->image]);
            }

            $updated++;
        }

        // Also try to match brands without external_id by name
        $unmatchedCrm = DB::table('brands')
            ->where(function ($q) {
                $q->whereNull('external_id')->orWhere('external_id', '');
            })
            ->whereNull('logo')
            ->orWhere('logo', '')
            ->get(['id', 'name', 'logo']);

        $nameMatched = 0;
        foreach ($unmatchedCrm as $brand) {
            $src = $source->first(fn($s) => strtolower($s->name) === strtolower($brand->name));
            if (!$src) continue;

            $this->line("  [NAME MATCH: {$brand->name}] → {$src->image}");

            if (!$isDry) {
                DB::table('brands')
                    ->where('id', $brand->id)
                    ->update(['logo' => $src->image, 'external_id' => $src->id]);
            }

            $nameMatched++;
        }

        $this->newLine();
        $this->info("Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Updated (external_id match)', $updated],
                ['Name-matched (no external_id)', $nameMatched],
                ['Skipped (already up-to-date)', $skipped],
                ['Not found in tunerstop', $notFound],
            ]
        );

        if ($isDry) {
            $this->warn('Dry run complete. Run without --dry-run to apply changes.');
        } else {
            $this->info('Brand image sync complete!');
        }

        return self::SUCCESS;
    }
}
