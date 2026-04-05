<?php

namespace App\Modules\Accounts\Actions;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountOnboarding;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Support\SubscriptionPlanCatalogResolver;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBusinessAccountRegistrationAction
{
    public function __construct(
        private readonly SubscriptionPlanCatalogResolver $planCatalogResolver,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $accountType = $this->resolveAccountType((string) $payload['account_mode']);
            $subscriptionPlan = $this->resolveSubscriptionPlan((string) $payload['plan_preference']);

            $this->guardCombinedAccountRequiresPaidPlan($accountType, $subscriptionPlan);
            $planCatalog = $this->planCatalogResolver->for($accountType->value, $subscriptionPlan->value);
            $requiresCheckout = $planCatalog->requiresStripeCheckout();

            $owner = User::query()->create([
                'name' => (string) $payload['business_name'],
                'email' => Str::lower(trim((string) $payload['email'])),
                'password' => (string) $payload['password'],
            ]);

            $account = Account::query()->create([
                'name' => (string) $payload['business_name'],
                'slug' => $this->uniqueSlugFor((string) $payload['business_name']),
                'account_type' => $accountType,
                'retail_enabled' => in_array($accountType, [AccountType::RETAILER, AccountType::BOTH], true),
                'wholesale_enabled' => in_array($accountType, [AccountType::SUPPLIER, AccountType::BOTH], true),
                'status' => AccountStatus::ACTIVE,
                'base_subscription_plan' => $subscriptionPlan,
                'reports_subscription_enabled' => false,
                'reports_customer_limit' => null,
                'created_by_user_id' => $owner->id,
            ]);

            $account->users()->attach($owner->id, [
                'role' => AccountRole::OWNER->value,
                'is_default' => true,
            ]);

            $workspaceCustomer = Customer::query()->updateOrCreate(
                [
                    'account_id' => $account->id,
                    'external_source' => 'business_owner_workspace',
                ],
                [
                    'customer_type' => $this->resolveWorkspaceCustomerType($accountType),
                    'business_name' => $account->name,
                    'email' => $owner->email,
                    'status' => 'active',
                    'website' => null,
                    'trade_license_number' => null,
                    'license_no' => null,
                    'instagram' => null,
                    'external_customer_id' => sprintf('account-%d', $account->id),
                ],
            );

            $subscription = AccountSubscription::query()->create([
                'account_id' => $account->id,
                'plan_code' => $subscriptionPlan,
                'status' => $requiresCheckout ? 'pending_checkout' : 'active',
                'reports_enabled' => false,
                'reports_customer_limit' => null,
                'starts_at' => $requiresCheckout ? null : now(),
                'trial_ends_at' => null,
                'billing_resume_token' => $requiresCheckout ? (string) Str::uuid() : null,
                'created_by_user_id' => $owner->id,
                'meta' => [
                    'created_via' => 'public_business_registration',
                    'billing_mode' => $planCatalog->billing_mode,
                    'plan_display_name' => $planCatalog->display_name,
                    'trial_days' => $planCatalog->trial_days,
                ],
            ]);

            $document = $payload['supporting_document'] ?? null;
            $documentPath = $document instanceof UploadedFile
                ? $document->store('account-onboardings', 'public')
                : null;

            $onboarding = AccountOnboarding::query()->create([
                'account_id' => $account->id,
                'owner_user_id' => $owner->id,
                'account_mode' => $accountType->value,
                'plan_preference' => $subscriptionPlan->value,
                'country' => $payload['country'] ? (string) $payload['country'] : null,
                'supporting_document_path' => $documentPath,
                'supporting_document_name' => $document instanceof UploadedFile ? $document->getClientOriginalName() : null,
                'registration_source' => $payload['registration_source'] ? (string) $payload['registration_source'] : null,
                'status' => 'completed',
                'accepts_terms' => (bool) $payload['accepts_terms'],
                'accepts_privacy' => (bool) $payload['accepts_privacy'],
                'meta' => [
                    'country' => $payload['country'] ? (string) $payload['country'] : null,
                ],
            ]);

            return [
                'owner' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ],
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'account_type' => $account->account_type?->value,
                    'account_type_label' => $account->account_type?->label(),
                    'retail_enabled' => (bool) $account->retail_enabled,
                    'wholesale_enabled' => (bool) $account->wholesale_enabled,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'plan_code' => $subscription->plan_code?->value,
                    'plan_label' => $planCatalog->display_name,
                    'reports_enabled' => (bool) $subscription->reports_enabled,
                    'status' => $subscription->status,
                ],
                'billing' => [
                    'requires_checkout' => $requiresCheckout,
                    'billing_mode' => $planCatalog->billing_mode,
                    'resume_token' => $subscription->billing_resume_token,
                    'trial_days' => $planCatalog->trial_days,
                    'plan_display_name' => $planCatalog->display_name,
                ],
                'workspace_customer' => [
                    'id' => $workspaceCustomer->id,
                    'customer_type' => $workspaceCustomer->customer_type,
                    'external_source' => $workspaceCustomer->external_source,
                ],
                'onboarding' => [
                    'id' => $onboarding->id,
                    'country' => $onboarding->country,
                    'document_uploaded' => $onboarding->supporting_document_path !== null,
                    'registration_source' => $onboarding->registration_source,
                    'status' => $onboarding->status,
                ],
            ];
        });
    }

    private function resolveAccountType(string $mode): AccountType
    {
        return match ($mode) {
            AccountType::SUPPLIER->value => AccountType::SUPPLIER,
            AccountType::BOTH->value => AccountType::BOTH,
            default => AccountType::RETAILER,
        };
    }

    private function resolveSubscriptionPlan(string $plan): SubscriptionPlan
    {
        return $plan === SubscriptionPlan::PREMIUM->value
            ? SubscriptionPlan::PREMIUM
            : SubscriptionPlan::BASIC;
    }

    private function resolveWorkspaceCustomerType(AccountType $accountType): string
    {
        return match ($accountType) {
            AccountType::SUPPLIER => 'wholesale',
            default => 'dealer',
        };
    }

    private function guardCombinedAccountRequiresPaidPlan(AccountType $accountType, SubscriptionPlan $subscriptionPlan): void
    {
        if ($accountType === AccountType::BOTH && $subscriptionPlan === SubscriptionPlan::BASIC) {
            throw ValidationException::withMessages([
                'plan_preference' => 'Retail + wholesale business accounts require a paid subscription.',
            ]);
        }
    }

    private function uniqueSlugFor(string $businessName): string
    {
        $baseSlug = Str::slug($businessName);
        $slug = $baseSlug !== '' ? $baseSlug : 'clockwork-account';
        $suffix = 1;

        while (Account::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'clockwork-account', $suffix);
        }

        return $slug;
    }
}
