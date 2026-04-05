<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountOnboarding;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\StripeSubscriptionCheckoutService;
use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_free_business_owner_account_subscription_onboarding_and_workspace_customer(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/auth/business-register', [
            'business_name' => 'Alpha Tyres Trading',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
            'country' => 'United Arab Emirates',
            'account_mode' => 'retailer',
            'plan_preference' => 'basic',
            'accepts_terms' => '1',
            'accepts_privacy' => '1',
            'registration_source' => 'clockwork-tyres-storefront',
            'trade_license' => UploadedFile::fake()->image('trade-license.png'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.owner.email', 'owner@alpha.test')
            ->assertJsonPath('data.account.account_type', 'retailer')
            ->assertJsonPath('data.subscription.plan_code', 'basic')
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.billing.requires_checkout', false)
            ->assertJsonPath('data.onboarding.document_uploaded', true);

        $owner = User::query()->where('email', 'owner@alpha.test')->firstOrFail();
        $account = Account::query()->where('created_by_user_id', $owner->id)->firstOrFail();
        $subscription = AccountSubscription::query()->where('account_id', $account->id)->firstOrFail();
        $onboarding = AccountOnboarding::query()->where('account_id', $account->id)->firstOrFail();
        $workspaceCustomer = Customer::query()
            ->where('account_id', $account->id)
            ->where('external_source', 'business_owner_workspace')
            ->firstOrFail();

        $this->assertSame('Alpha Tyres Trading', $account->name);
        $this->assertSame('alpha-tyres-trading', $account->slug);
        $this->assertSame('retailer', $account->account_type->value);
        $this->assertTrue($account->retail_enabled);
        $this->assertFalse($account->wholesale_enabled);
        $this->assertSame('basic', $subscription->plan_code->value);
        $this->assertSame('active', $subscription->status);
        $this->assertFalse($subscription->reports_enabled);
        $this->assertNull($subscription->billing_resume_token);
        $this->assertSame('completed', $onboarding->status);
        $this->assertSame('clockwork-tyres-storefront', $onboarding->registration_source);
        $this->assertSame('United Arab Emirates', $onboarding->country);
        $this->assertNotNull($onboarding->supporting_document_path);
        $this->assertSame('trade-license.png', $onboarding->supporting_document_name);
        $this->assertSame('dealer', $workspaceCustomer->customer_type);
        $this->assertSame('Alpha Tyres Trading', $workspaceCustomer->business_name);
        $this->assertSame('owner@alpha.test', $workspaceCustomer->email);
        $this->assertCount(0, $owner->roles);

        $membership = DB::table('account_user')
            ->where('account_id', $account->id)
            ->where('user_id', $owner->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame('owner', $membership->role);
        $this->assertSame(1, (int) $membership->is_default);

        Storage::disk('public')->assertExists($onboarding->supporting_document_path);
    }

    public function test_it_creates_a_paid_account_and_returns_a_stripe_trial_checkout_url(): void
    {
        Storage::fake('public');

        app()->instance(StripeSubscriptionCheckoutService::class, new class extends StripeSubscriptionCheckoutService {
            public function createCheckoutSession(Account $account, AccountSubscription $subscription, \App\Modules\Accounts\Models\SubscriptionPlanCatalog $planCatalog): array
            {
                $subscription->forceFill([
                    'stripe_checkout_session_id' => 'cs_test_trial_checkout',
                ])->save();

                return [
                    'requires_checkout' => true,
                    'billing_mode' => 'stripe_subscription',
                    'status' => $subscription->status,
                    'resume_token' => $subscription->billing_resume_token,
                    'plan_display_name' => $planCatalog->display_name,
                    'trial_days' => 14,
                    'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_trial_checkout',
                    'session_id' => 'cs_test_trial_checkout',
                ];
            }
        });

        $response = $this->post('/api/auth/business-register', [
            'business_name' => 'Beta Tyres Trading',
            'email' => 'owner@beta.test',
            'password' => 'clockwork123',
            'country' => 'United Arab Emirates',
            'account_mode' => 'retailer',
            'plan_preference' => 'premium',
            'accepts_terms' => '1',
            'accepts_privacy' => '1',
            'registration_source' => 'clockwork-tyres-storefront',
            'trade_license' => UploadedFile::fake()->image('trade-license.png'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.subscription.plan_code', 'premium')
            ->assertJsonPath('data.subscription.status', 'pending_checkout')
            ->assertJsonPath('data.billing.requires_checkout', true)
            ->assertJsonPath('data.billing.trial_days', 14)
            ->assertJsonPath('data.billing.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_trial_checkout');

        $account = Account::query()->where('name', 'Beta Tyres Trading')->firstOrFail();
        $subscription = AccountSubscription::query()->where('account_id', $account->id)->firstOrFail();

        $this->assertSame('pending_checkout', $subscription->status);
        $this->assertNotNull($subscription->billing_resume_token);
        $this->assertSame('premium', $subscription->plan_code->value);
    }

    public function test_it_resumes_a_paid_checkout_from_the_resume_token(): void
    {
        $owner = User::query()->create([
            'name' => 'Resume Owner',
            'email' => 'resume@clockwork.test',
            'password' => 'password',
        ]);

        $account = Account::query()->create([
            'name' => 'Resume Tyres',
            'slug' => 'resume-tyres',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => $owner->id,
        ]);

        $subscription = AccountSubscription::query()->create([
            'account_id' => $account->id,
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'pending_checkout',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'billing_resume_token' => 'resume-token-123',
            'created_by_user_id' => $owner->id,
        ]);

        app()->instance(StripeSubscriptionCheckoutService::class, new class($subscription) extends StripeSubscriptionCheckoutService {
            public function __construct(private readonly AccountSubscription $subscription)
            {
            }

            public function resumeCheckout(AccountSubscription $subscription): array
            {
                return [
                    'requires_checkout' => true,
                    'billing_mode' => 'stripe_subscription',
                    'status' => $subscription->status,
                    'resume_token' => $subscription->billing_resume_token,
                    'plan_display_name' => 'Plus',
                    'trial_days' => 14,
                    'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_resume',
                    'session_id' => 'cs_test_resume',
                ];
            }
        });

        $this->postJson('/api/auth/business-register/billing/resume', [
            'resume_token' => 'resume-token-123',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.billing.requires_checkout', true)
            ->assertJsonPath('data.billing.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_resume')
            ->assertJsonPath('data.billing.trial_days', 14);
    }

    public function test_it_redirects_to_login_after_successful_checkout_sync(): void
    {
        $owner = User::query()->create([
            'name' => 'Trial Owner',
            'email' => 'trial@clockwork.test',
            'password' => 'password',
        ]);

        $account = Account::query()->create([
            'name' => 'Trial Tyres',
            'slug' => 'trial-tyres',
            'account_type' => AccountType::RETAILER,
            'retail_enabled' => true,
            'wholesale_enabled' => false,
            'status' => AccountStatus::ACTIVE,
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'reports_subscription_enabled' => false,
            'reports_customer_limit' => null,
            'created_by_user_id' => $owner->id,
        ]);

        $subscription = AccountSubscription::query()->create([
            'account_id' => $account->id,
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'trialing',
            'reports_enabled' => false,
            'reports_customer_limit' => null,
            'billing_resume_token' => 'trial-resume-token',
            'created_by_user_id' => $owner->id,
        ]);

        app()->instance(StripeSubscriptionCheckoutService::class, new class($subscription) extends StripeSubscriptionCheckoutService {
            public function __construct(private readonly AccountSubscription $subscription)
            {
            }

            public function syncCheckoutSession(string $sessionId): AccountSubscription
            {
                return $this->subscription->loadMissing('account.createdBy');
            }
        });

        $response = $this->get('/api/auth/business-register/billing/success?session_id=cs_test_success');

        $expectedBaseUrl = rtrim((string) config('services.wholesale.frontend_url', 'http://localhost:4200'), '/');

        $response->assertRedirect($expectedBaseUrl.'/login?registered=1&billing=trial-started&email=trial%40clockwork.test');
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

    public function test_it_rejects_combined_accounts_on_the_free_plan(): void
    {
        Storage::fake('public');

        $response = $this->withHeader('Accept', 'application/json')->post('/api/auth/business-register', [
            'business_name' => 'Hybrid Counter Business',
            'email' => 'owner@hybrid.test',
            'password' => 'clockwork123',
            'country' => 'United Arab Emirates',
            'account_mode' => 'both',
            'plan_preference' => 'basic',
            'accepts_terms' => '1',
            'accepts_privacy' => '1',
            'trade_license' => UploadedFile::fake()->create('trade-license.pdf', 100),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan_preference']);
    }
}
