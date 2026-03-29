<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Support\TyreGridLayout;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TyreGridLayoutTest extends TestCase
{
    #[Test]
    public function it_exposes_the_crm_grid_columns_for_the_tyre_scaffold(): void
    {
        $columns = TyreGridLayout::columns();

        $this->assertSame('sku', $columns[0]['dataIndx']);
        $this->assertSame('Brand', $columns[1]['title']);
        $this->assertSame('retail_price', $columns[6]['dataIndx']);
        $this->assertSame('wholesale_lvl3_price', $columns[9]['dataIndx']);
        $this->assertSame('availability_note', $columns[10]['dataIndx']);
        $this->assertFalse($columns[6]['editable']);
    }

    #[Test]
    public function it_exposes_toolbar_actions_for_the_tyre_grid_shell(): void
    {
        $actions = TyreGridLayout::toolbarActions();

        $this->assertCount(2, $actions);
        $this->assertSame('import-tyres', $actions[0]['id']);
        $this->assertTrue($actions[0]['disabled']);
        $this->assertSame('refresh-grid', $actions[1]['id']);
        $this->assertFalse($actions[1]['disabled']);
    }
}
