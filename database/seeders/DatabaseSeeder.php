<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        $this->seedPanelUser(
            name: 'Clockwork Super Admin',
            email: 'superadmin@clockwork.local',
            role: 'super_admin',
        );

        $this->seedPanelUser(
            name: 'Clockwork Admin',
            email: 'admin@clockwork.local',
            role: 'admin',
        );

        // Preserve the original seeded admin for backwards-compatible local access.
        $this->seedPanelUser(
            name: 'Clockwork Legacy Admin',
            email: 'test@example.com',
            role: 'super_admin',
        );
        $this->call([
            ClockworkTyresDemoSeeder::class,
        ]);
    }

    private function seedPanelUser(string $name, string $email, string $role): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([$role]);
    }
}
