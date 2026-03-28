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

    private const REPORT_SLUGS_BY_PERMISSION = [
        'view_sales_reports' => [
            '/admin/reports/sales-by-brand',
            '/admin/reports/sales-by-model',
            '/admin/reports/sales-by-size',
            '/admin/reports/sales-by-vehicle',
            '/admin/reports/sales-by-dealer',
            '/admin/reports/sales-by-sku',
            '/admin/reports/sales-by-channel',
            '/admin/reports/sales-by-team',
            '/admin/reports/sales-by-categories',
        ],
        'view_profit_reports' => [
            '/admin/reports/profit-by-order',
            '/admin/reports/profit-by-brand',
            '/admin/reports/profit-by-model',
            '/admin/reports/profit-by-size',
            '/admin/reports/profit-by-vehicle',
            '/admin/reports/profit-by-dealer',
            '/admin/reports/profit-by-sku',
            '/admin/reports/profit-by-month',
            '/admin/reports/profit-by-salesman',
            '/admin/reports/profit-by-channel',
            '/admin/reports/profit-by-categories',
        ],
        'view_inventory_reports' => [
            '/admin/reports/inventory-by-sku',
            '/admin/reports/inventory-by-brand',
            '/admin/reports/inventory-by-model',
        ],
        'view_dealer_reports' => [
            '/admin/reports/dealer-sales-by-brand',
            '/admin/reports/dealer-sales-by-model',
        ],
        'view_team_reports' => [
            '/admin/reports/orders-by-user',
        ],
    ];

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

    public function test_each_report_page_is_accessible_with_its_matching_permission_family(): void
    {
        foreach (self::REPORT_SLUGS_BY_PERMISSION as $permission => $paths) {
            $user = $this->createUserWithPermissions([
                'view_reports',
                $permission,
            ]);

            foreach ($paths as $path) {
                $this->actingAs($user)
                    ->get($path)
                    ->assertOk();
            }
        }
    }

    public function test_report_pages_render_requested_brand_category_and_search_filters(): void
    {
        $user = $this->createUserWithPermissions([
            'view_reports',
            'view_sales_reports',
            'view_profit_reports',
            'view_inventory_reports',
        ]);

        $this->actingAs($user)
            ->get('/admin/reports/sales-by-model')
            ->assertOk()
            ->assertSee('name="brand"', false);

        $this->actingAs($user)
            ->get('/admin/reports/sales-by-sku')
            ->assertOk()
            ->assertSee('name="brand"', false)
            ->assertSee('name="search"', false);

        $this->actingAs($user)
            ->get('/admin/reports/sales-by-channel')
            ->assertOk()
            ->assertSee('name="category"', false);

        $this->actingAs($user)
            ->get('/admin/reports/profit-by-sku')
            ->assertOk()
            ->assertSee('name="brand"', false);

        $this->actingAs($user)
            ->get('/admin/reports/inventory-by-model')
            ->assertOk()
            ->assertSee('name="brand"', false);

        $this->actingAs($user)
            ->get('/admin/reports/inventory-by-sku')
            ->assertOk()
            ->assertSee('name="brand"', false)
            ->assertSee('name="category"', false)
            ->assertSee('name="search"', false);
    }

    protected function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}