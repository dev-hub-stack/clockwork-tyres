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

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $salesRole = Role::firstOrCreate(['name' => 'sales_rep']);

        // Assign admin role to specific users (e.g., Admin User)
        $adminUser = User::where('email', 'admin@tunerstop.com')->first();
        if ($adminUser) {
            $adminUser->assignRole($adminRole);
        } else {
            // Create admin user if not exists
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@tunerstop.com',
                'password' => bcrypt('password'),
            ]);
            $admin->assignRole($adminRole);
        }

        // Assign accountant role
        $accountantUser = User::where('email', 'accountant@tunerstop.com')->first();
        if ($accountantUser) {
            $accountantUser->assignRole($accountantRole);
        } else {
             $acc = User::create([
                'name' => 'Accountant User',
                'email' => 'accountant@tunerstop.com',
                'password' => bcrypt('password'),
            ]);
            $acc->assignRole($accountantRole);
        }
        
        // Assign sales role to Test User
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            $testUser->assignRole($salesRole);
        }
    }
}
