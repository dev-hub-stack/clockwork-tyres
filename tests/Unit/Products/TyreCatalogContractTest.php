<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Support\TyreCatalogContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TyreCatalogContractTest extends TestCase
{
    #[Test]
    public function blueprint_matches_the_sample_sheet_launch_contract(): void
    {
        $blueprint = TyreCatalogContract::blueprint();

        $this->assertSame(1, $blueprint['version']);
        $this->assertSame('tyres', $blueprint['category']);
        $this->assertSame('Sheet1', $blueprint['source_sheet']['sheet_name']);
        $this->assertSame(
            ['source_file', 'sheet_name', 'row_number', 'raw_row'],
            $blueprint['ingest_envelope']
        );
        $this->assertSame(
            ['identity', 'fitment', 'attributes', 'pricing', 'media', 'metadata', 'audit'],
            $blueprint['sections']
        );
        $this->assertSame(
            ['retail', 'wholesale_lvl1', 'wholesale_lvl2', 'wholesale_lvl3'],
            $blueprint['pricing_levels']
        );
        $this->assertSame('Retail_price', $blueprint['pricing_columns']['retail_price']['source_header']);
        $this->assertCount(24, $blueprint['source_columns']);
        $this->assertContains('full_size', $blueprint['required_fields']);
        $this->assertContains('runflat', $blueprint['boolean_like_fields']);
        $this->assertContains('product_image_3', $blueprint['image_fields']);
        $this->assertSame(
            ['brand', 'model', 'full_size', 'year'],
            $blueprint['grouping_rules']['storefront_merge_key']['fields']
        );
        $this->assertSame('dot', $blueprint['grouping_rules']['storefront_merge_key']['field_sources']['year']);
        $this->assertContains('sku', $blueprint['grouping_rules']['storefront_merge_key']['ignores']);
        $this->assertContains('tyres are the launch category', $blueprint['launch_notes']);
        $this->assertContains('wheels remain a future category', $blueprint['launch_notes']);
        $this->assertContains(
            'storefront grouping must ignore supplier SKU and merge by brand, model, size, and year',
            $blueprint['launch_notes']
        );
        $this->assertContains(
            'full_size is derived from width, height, and rim_size and should be treated as the composed canonical size',
            $blueprint['validation_notes']
        );
        $this->assertContains(
            'DOT may be provided as year only (for example 2025) or as week plus year (for example 2625 for week 26 of 2025); grouping should normalize both forms to the same year',
            $blueprint['validation_notes']
        );
    }
}
