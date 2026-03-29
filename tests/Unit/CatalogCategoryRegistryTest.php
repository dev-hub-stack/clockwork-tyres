<?php

namespace Tests\Unit;

use App\Modules\Products\Support\CatalogCategoryRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogCategoryRegistryTest extends TestCase
{
    #[Test]
    public function it_marks_tyres_as_the_launch_category(): void
    {
        $this->assertSame(CatalogCategoryRegistry::TYRES, CatalogCategoryRegistry::launchCategory());
        $this->assertTrue(CatalogCategoryRegistry::isLaunchCategory(CatalogCategoryRegistry::TYRES));
        $this->assertFalse(CatalogCategoryRegistry::isLaunchCategory(CatalogCategoryRegistry::WHEELS));
    }

    #[Test]
    public function it_keeps_wheels_defined_but_disabled_for_launch(): void
    {
        $this->assertTrue(CatalogCategoryRegistry::isEnabled(CatalogCategoryRegistry::TYRES));
        $this->assertFalse(CatalogCategoryRegistry::isEnabled(CatalogCategoryRegistry::WHEELS));

        $this->assertCount(1, CatalogCategoryRegistry::enabledCategories());
        $this->assertSame('Tyres', CatalogCategoryRegistry::definition(CatalogCategoryRegistry::TYRES)['label']);
        $this->assertSame(['vehicle', 'size'], CatalogCategoryRegistry::searchModes(CatalogCategoryRegistry::TYRES));
    }
}
