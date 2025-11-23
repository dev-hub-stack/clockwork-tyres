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

        // Create permissions for each module
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

        // Cleanup: Delete permissions that are no longer in the list
        Permission::whereNotIn('name', $permissions)->delete();

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $salesRole = Role::firstOrCreate(['name' => 'sales_rep']);
        $warehouseRole = Role::firstOrCreate(['name' => 'warehouse_manager']);

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Accountant permissions
        $accountantRole->syncPermissions([
            'view_dashboard',
            'view_invoices',
            'view_customers',
        ]);

        // Sales Rep permissions
        $salesRole->syncPermissions([
            'view_dashboard',
            'view_quotes', 'create_quotes', 'edit_quotes',
            'view_invoices',
            'view_customers', 'create_customers', 'edit_customers',
        ]);

        // Warehouse Manager permissions
        $warehouseRole->syncPermissions([
            'view_dashboard',
            'view_inventory',
            'view_warehouses',
            'view_consignments', 'edit_consignments',
            'view_products',
        ]);

        // Assign roles to users
        $adminUser = User::where('email', 'admin@tunerstop.com')->first();
        if ($adminUser) {
            $adminUser->assignRole($adminRole);
        } else {
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@tunerstop.com',
                'password' => bcrypt('password'),
            ]);
            $admin->assignRole($adminRole);
        }

        $accountantUser = User::where('email', 'accountant@tunerstop.com')->first();
        if ($accountantUser) {
            $accountantUser->syncRoles([$accountantRole]);
        } else {
             $acc = User::create([
                'name' => 'Accountant User',
                'email' => 'accountant@tunerstop.com',
                'password' => bcrypt('password'),
            ]);
            $acc->assignRole($accountantRole);
        }
        
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            $testUser->syncRoles([$salesRole]);
        }
    }
}
