<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_super_admin_sees_platform_dashboard_instead_of_business_ops_order_sheet(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->followingRedirects()
            ->get('/admin')
            ->assertOk()
            ->assertSee('Platform Dashboard')
            ->assertSee('Business Accounts')
            ->assertSee('Products Listed')
            ->assertSee('Retail Transaction Value')
            ->assertSee('Wholesale Transaction Value')
            ->assertSee('Procurement Queue Snapshot')
            ->assertDontSee('Pending Orders - Order Sheet');
    }
}
