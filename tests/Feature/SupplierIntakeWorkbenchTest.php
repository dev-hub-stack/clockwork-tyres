<?php

namespace Tests\Feature;

use App\Filament\Pages\SupplierIntakeWorkbench;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupplierIntakeWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'view_quotes', 'guard_name' => 'web']);
    }

    public function test_supplier_intake_workbench_requires_quote_visibility_and_renders_supplier_flow_copy(): void
    {
        $supplierUser = User::factory()->create();
        $supplierUser->givePermissionTo('view_quotes');

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get('/admin/supplier-intake-workbench')
            ->assertForbidden();

        $this->actingAs($supplierUser)
            ->get('/admin/supplier-intake-workbench')
            ->assertOk()
            ->assertSee('Supplier Intake Workbench')
            ->assertSee('Quotes & Proformas')
            ->assertSee('Quote approval converts to invoice');

        $page = app(SupplierIntakeWorkbench::class);
        $page->mount();

        $this->assertTrue(SupplierIntakeWorkbench::canAccess());
        $this->assertCount(9, $page->statusRail);
        $this->assertSame('submitted', $page->statusRail[0]['key']);
        $this->assertSame('PROC-1001', $page->incomingRequests[0]['request_number']);
    }
}
