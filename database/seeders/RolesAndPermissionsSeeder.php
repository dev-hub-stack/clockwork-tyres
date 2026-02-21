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
            // Dashboard
            'view_dashboard',

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

            // Inventory - Warehouse
            'view_inventory',
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

            // Warranty Claims
            'view_warranty_claims',
            'create_warranty_claims',
            'edit_warranty_claims',
            'delete_warranty_claims',

            // Reports (restricted)
            'view_reports',

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

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Legacy admin — all permissions (backward compatibility)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // Accountant — all except reports, settings, and deletes
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'view_dashboard',
            'view_quotes', 'create_quotes', 'edit_quotes',
            'view_invoices', 'create_invoices', 'edit_invoices',
            'view_consignments', 'create_consignments', 'edit_consignments',
            'view_inventory',
            'view_warehouses', 'create_warehouses', 'edit_warehouses',
            'view_products', 'create_products', 'edit_products',
            'view_categories', 'create_categories', 'edit_categories',
            'view_customers', 'create_customers', 'edit_customers',
            'view_warranty_claims', 'create_warranty_claims', 'edit_warranty_claims',
            'view_expenses',
            'view_users', 'create_users', 'edit_users',
        ]);

        // Sales — all except reports, settings, expenses, and deletes
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->syncPermissions([
            'view_dashboard',
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

        // Marketing — view only: invoices and customers
        $marketing = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        $marketing->syncPermissions([
            'view_dashboard',
            'view_invoices',
            'view_customers',
        ]);

        // Remove obsolete roles if they exist
        Role::whereIn('name', ['sales_rep', 'warehouse_manager'])->delete();

        $this->command->info('Roles and permissions seeded successfully.');
        $this->command->info('Roles: super_admin, admin (legacy), accountant, sales, marketing');
    }
}
