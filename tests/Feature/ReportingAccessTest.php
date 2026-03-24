<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_reports_index_and_legacy_sales_page_require_sales_report_permission(): void
    {
        $limitedUser = $this->createUserWithPermissions([
            'view_reports',
        ]);

        $this->actingAs($limitedUser)
            ->get('/admin/reports')
            ->assertForbidden();

        $this->actingAs($limitedUser)
            ->get('/admin/sales-dashboard')
            ->assertForbidden();

        $salesUser = $this->createUserWithPermissions([
            'view_reports',
            'view_sales_reports',
        ]);

        $this->actingAs($salesUser)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Sales Reports');

        $this->actingAs($salesUser)
            ->get('/admin/sales-dashboard')
            ->assertOk()
            ->assertSee('Sales Dashboard')
            ->assertSee('Legacy Sales Analytics');
    }

    public function test_team_report_page_uses_team_permission_family(): void
    {
        $teamUser = $this->createUserWithPermissions([
            'view_reports',
            'view_team_reports',
        ]);

        $this->actingAs($teamUser)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Team Reports')
            ->assertSee('Orders by User');

        $this->actingAs($teamUser)
            ->get('/admin/sales-dashboard')
            ->assertForbidden();
    }

    protected function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}