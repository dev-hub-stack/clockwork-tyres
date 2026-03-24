<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions
        $permissions = [
            // Dashboard cards (per-card visibility)
            'view_dashboard',
            'view_pending_orders_card',
            'view_monthly_revenue_card',
            'view_today_orders_card',
            'view_pending_warranty_card',

            // Sales - Quotes
            'view_quotes',
            'create_quotes',
            'edit_quotes',
            'delete_quotes',

            // Sales - Invoices
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'delete_invoices',

            // Inventory - Consignments
            'view_consignments',
            'create_consignments',
            'edit_consignments',
            'delete_consignments',

            // Inventory - Warehouse & Grid
            'view_inventory',
            'edit_inventory_grid',   // super_admin only: direct cell editing
            'view_bulk_transfer',    // button: transfer stock between warehouses
            'view_add_inventory',    // button: bulk add qty to warehouse/incoming
            'view_warehouses',
            'create_warehouses',
            'edit_warehouses',
            'delete_warehouses',

            // Products
            'view_products',
            'create_products',
            'edit_products',
            'delete_products',

            // Categories
            'view_categories',
            'create_categories',
            'edit_categories',
            'delete_categories',

            // Customers
            'view_customers',
            'create_customers',
            'edit_customers',
            'delete_customers',
            'receive_wholesale_inquiries',

            // Warranty Claims
            'view_warranty_claims',
            'create_warranty_claims',
            'edit_warranty_claims',
            'delete_warranty_claims',

            // Reports (restricted)
            'view_reports',
            'view_sales_reports',
            'view_profit_reports',
            'view_inventory_reports',
            'view_dealer_reports',
            'view_team_reports',
            'export_reports',

            // Financial (restricted)
            'view_expenses',

            // Administration
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_settings',
            'edit_settings',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Remove permissions no longer in the list
        Permission::whereNotIn('name', $permissions)->delete();

        // -------------------------------------------------------
        // Define roles (delete & recreate for clean permissions)
        // -------------------------------------------------------

        // Super Admin — full control including direct grid cell editing
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — full control except direct grid cell editing (super_admin only)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(
            Permission::where('name', '!=', 'edit_inventory_grid')->get()
        );

        // Accountant — no user management, no reports, no deletes, no cell editing
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'view_dashboard',
            'view_pending_orders_card',
            'view_monthly_revenue_card',
            'view_today_orders_card',
            'view_pending_warranty_card',
            'view_quotes', 'create_quotes', 'edit_quotes',
            'view_invoices', 'create_invoices', 'edit_invoices',
            'view_consignments', 'create_consignments', 'edit_consignments',
            'view_inventory', 'view_bulk_transfer', 'view_add_inventory',
            'view_warehouses', 'create_warehouses', 'edit_warehouses',
            'view_products', 'create_products', 'edit_products',
            'view_categories', 'create_categories', 'edit_categories',
            'view_customers', 'create_customers', 'edit_customers',
            'view_warranty_claims', 'create_warranty_claims', 'edit_warranty_claims',
            'view_expenses',
        ]);

        // Sales — view/create/edit only, no inventory editing, no reports
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->syncPermissions([
            'view_dashboard',
            'view_pending_orders_card',
            'view_today_orders_card',
            'view_quotes', 'create_quotes', 'edit_quotes',
            'view_invoices', 'create_invoices', 'edit_invoices',
            'view_consignments', 'create_consignments', 'edit_consignments',
            'view_inventory',
            'view_warehouses',
            'view_products',
            'view_categories',
            'view_customers', 'create_customers', 'edit_customers',
            'view_warranty_claims', 'create_warranty_claims', 'edit_warranty_claims',
        ]);

        // Marketing — view only
        $marketing = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        $marketing->syncPermissions([
            'view_dashboard',
            'view_pending_orders_card',
            'view_today_orders_card',
            'view_invoices',
            'view_customers',
        ]);

        // Remove obsolete roles
        Role::whereIn('name', ['sales_rep', 'warehouse_manager'])->delete();

        $this->command->info('Roles and permissions seeded successfully.');
        $this->command->info('super_admin: all permissions including edit_inventory_grid');
        $this->command->info('admin: all permissions EXCEPT edit_inventory_grid');
        $this->command->info('Other roles: accountant, sales, marketing');
        $this->command->info('Report module permissions: view_sales_reports, view_profit_reports, view_inventory_reports, view_dealer_reports, view_team_reports, export_reports');
    }
}
