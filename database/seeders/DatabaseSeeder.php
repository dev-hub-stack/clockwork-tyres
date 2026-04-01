<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
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

        $this->seedDemoBusinessAccounts();
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

    private function seedDemoBusinessAccounts(): void
    {
        $adminUser = User::query()->where('email', 'admin@clockwork.local')->first();

        if (! $adminUser) {
            return;
        }

        $retailAccount = Account::query()->updateOrCreate(
            ['slug' => 'clockwork-retail-demo'],
            [
                'name' => 'Clockwork Retail Demo',
                'account_type' => AccountType::RETAILER,
                'retail_enabled' => true,
                'wholesale_enabled' => false,
                'status' => AccountStatus::ACTIVE,
                'base_subscription_plan' => SubscriptionPlan::PREMIUM,
                'reports_subscription_enabled' => false,
                'reports_customer_limit' => null,
                'created_by_user_id' => $adminUser->id,
            ],
        );

        $supplyAccount = Account::query()->updateOrCreate(
            ['slug' => 'clockwork-supply-demo'],
            [
                'name' => 'Clockwork Supply Demo',
                'account_type' => AccountType::BOTH,
                'retail_enabled' => true,
                'wholesale_enabled' => true,
                'status' => AccountStatus::ACTIVE,
                'base_subscription_plan' => SubscriptionPlan::PREMIUM,
                'reports_subscription_enabled' => true,
                'reports_customer_limit' => 250,
                'created_by_user_id' => $adminUser->id,
            ],
        );

        $adminUser->accounts()->syncWithoutDetaching([
            $retailAccount->id => [
                'role' => AccountRole::OWNER->value,
                'is_default' => true,
            ],
            $supplyAccount->id => [
                'role' => AccountRole::OWNER->value,
                'is_default' => false,
            ],
        ]);
    }
}
