<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Support\TyreCatalogContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TyreCatalogContractTest extends TestCase
{
    #[Test]
    public function blueprint_keeps_the_launch_contract_generic(): void
    {
        $blueprint = TyreCatalogContract::blueprint();

        $this->assertSame(1, $blueprint['version']);
        $this->assertSame('tyres', $blueprint['category']);
        $this->assertSame(
            ['source_file', 'sheet_name', 'row_number', 'raw_row'],
            $blueprint['ingest_envelope']
        );
        $this->assertSame(
            ['identity', 'merchandising', 'pricing', 'inventory', 'fitment', 'media', 'metadata', 'audit'],
            $blueprint['sections']
        );
        $this->assertSame(
            ['retail', 'wholesale_lvl1', 'wholesale_lvl2', 'wholesale_lvl3'],
            $blueprint['pricing_levels']
        );
        $this->assertContains('tyres are the launch category', $blueprint['launch_notes']);
        $this->assertContains('wheels remain a future category', $blueprint['launch_notes']);
    }
}
