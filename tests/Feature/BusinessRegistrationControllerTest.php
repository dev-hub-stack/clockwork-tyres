<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountOnboarding;
use App\Modules\Accounts\Models\AccountSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_business_owner_account_subscription_and_onboarding_record(): void
    {
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $response = $this->post('/api/auth/business-register', [
            'business_name' => 'Alpha Tyres Trading',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
            'country' => 'United Arab Emirates',
            'account_mode' => 'both',
            'plan_preference' => 'premium',
            'accepts_terms' => '1',
            'accepts_privacy' => '1',
            'registration_source' => 'clockwork-tyres-storefront',
            'trade_license' => UploadedFile::fake()->image('trade-license.png'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.owner.email', 'owner@alpha.test')
            ->assertJsonPath('data.account.account_type', 'both')
            ->assertJsonPath('data.subscription.plan_code', 'premium')
            ->assertJsonPath('data.onboarding.document_uploaded', true);

        $owner = User::query()->where('email', 'owner@alpha.test')->firstOrFail();
        $account = Account::query()->where('created_by_user_id', $owner->id)->firstOrFail();
        $subscription = AccountSubscription::query()->where('account_id', $account->id)->firstOrFail();
        $onboarding = AccountOnboarding::query()->where('account_id', $account->id)->firstOrFail();

        $this->assertSame('Alpha Tyres Trading', $account->name);
        $this->assertSame('alpha-tyres-trading', $account->slug);
        $this->assertSame('both', $account->account_type->value);
        $this->assertTrue($account->retail_enabled);
        $this->assertTrue($account->wholesale_enabled);
        $this->assertSame('premium', $subscription->plan_code->value);
        $this->assertFalse($subscription->reports_enabled);
        $this->assertSame('completed', $onboarding->status);
        $this->assertSame('clockwork-tyres-storefront', $onboarding->registration_source);
        $this->assertSame('United Arab Emirates', $onboarding->country);
        $this->assertNotNull($onboarding->supporting_document_path);
        $this->assertSame('trade-license.png', $onboarding->supporting_document_name);
        $this->assertTrue($owner->hasRole('admin'));

        $membership = DB::table('account_user')
            ->where('account_id', $account->id)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertSame(1, (int) $membership->is_default);

        Storage::disk('public')->assertExists($onboarding->supporting_document_path);
    }

    public function test_it_rejects_duplicate_owner_email_addresses(): void
    {
        User::query()->create([
            'name' => 'Existing Owner',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/api/auth/business-register', [
            'business_name' => 'Another Business',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
            'country' => 'United Arab Emirates',
            'account_mode' => 'retailer',
            'plan_preference' => 'basic',
            'accepts_terms' => '1',
            'accepts_privacy' => '1',
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
