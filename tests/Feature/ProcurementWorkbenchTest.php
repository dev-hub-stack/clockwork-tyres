<?php

namespace Tests\Feature;

use App\Filament\Pages\ProcurementWorkbench;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProcurementWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
    }

    public function test_procurement_workbench_shows_grouped_supplier_sections_and_unified_submission_copy(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view_quotes');

        $this->actingAs($user)
            ->get('/admin/procurement-workbench')
            ->assertOk()
            ->assertSee('Grouped supplier cart')
            ->assertSee('One workbench, supplier-separated sections')
            ->assertSee('North Coast Tyres')
            ->assertSee('Desert Line Trading')
            ->assertSee('One place order, split per supplier behind the scenes')
            ->assertSee('Supplier order #1')
            ->assertSee('Supplier order #2');

        $page = app(ProcurementWorkbench::class);
        $page->mount();

        $this->assertCount(2, $page->supplierGroups);
        $this->assertSame('One place order, split per supplier behind the scenes', $page->placeOrderCallout['title']);
        $this->assertSame(2, $page->requestSummary[0]['value']);
        $this->assertSame(4, $page->requestSummary[1]['value']);
    }
}
