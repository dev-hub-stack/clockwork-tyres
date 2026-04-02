<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminOperationalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'view_inventory',
            'view_products',
            'view_quotes',
            'view_customers',
            'view_reports',
            'view_users',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_is_blocked_from_operational_modules_but_keeps_governance_and_reporting_access(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $superAdmin->givePermissionTo([
            'view_inventory',
            'view_products',
            'view_quotes',
            'view_customers',
            'view_reports',
            'view_users',
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin/inventory-grid')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/products-grid')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/procurement-requests')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get('/admin/accounts')
            ->assertOk()
            ->assertSee('Business Accounts');

        $this->actingAs($superAdmin)
            ->get('/admin/activity-logs')
            ->assertOk();
    }
}
