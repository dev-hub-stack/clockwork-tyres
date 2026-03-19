<?php

/**
 * Create Admin User
 * 
 * Run: php create_admin_user.php
 */


$app = require_once dirname(__DIR__) . '/bootstrap.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "\n=== Creating Admin User ===\n\n";

try {
    // Check if admin user already exists
    $existingAdmin = User::where('email', 'admin@reporting.com')->first();
    
    if ($existingAdmin) {
        echo "⚠ Admin user already exists!\n";
        echo "Email: {$existingAdmin->email}\n";
        echo "Name: {$existingAdmin->name}\n";
        echo "ID: {$existingAdmin->id}\n\n";
        
        // Update password anyway
        $existingAdmin->password = Hash::make('password');
        $existingAdmin->save();
        echo "✓ Password reset to 'password'\n\n";
    } else {
        // Create new admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@reporting.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        
        echo "✓ Admin user created successfully!\n\n";
        echo "Login Credentials:\n";
        echo "==================\n";
        echo "Email: admin@reporting.com\n";
        echo "Password: password\n";
        echo "User ID: {$admin->id}\n\n";
    }
    
    echo "You can now login to Filament at: http://localhost:8000/admin\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
