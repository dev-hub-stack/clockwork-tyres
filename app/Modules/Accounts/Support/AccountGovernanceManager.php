<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountGovernanceManager
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizePayload(array $input, bool $creating = true, ?int $accountId = null): array
    {
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($slug === '') {
            $slug = Str::slug((string) ($input['name'] ?? ''));
        }

        $normalized = [
            'name' => trim((string) ($input['name'] ?? '')),
            'slug' => $slug,
            'account_type' => (string) ($input['account_type'] ?? AccountType::RETAILER->value),
            'retail_enabled' => (bool) ($input['retail_enabled'] ?? false),
            'wholesale_enabled' => (bool) ($input['wholesale_enabled'] ?? false),
            'status' => (string) ($input['status'] ?? AccountStatus::ACTIVE->value),
            'base_subscription_plan' => (string) ($input['base_subscription_plan'] ?? SubscriptionPlan::BASIC->value),
            'reports_subscription_enabled' => (bool) ($input['reports_subscription_enabled'] ?? false),
            'reports_customer_limit' => blank($input['reports_customer_limit'] ?? null)
                ? null
                : (int) $input['reports_customer_limit'],
        ];

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($normalized, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                $creating
                    ? Rule::unique('accounts', 'slug')
                    : Rule::unique('accounts', 'slug')->ignore($accountId),
            ],
            'account_type' => ['required', Rule::in(AccountType::values())],
            'retail_enabled' => ['boolean'],
            'wholesale_enabled' => ['boolean'],
            'status' => ['required', Rule::in(AccountStatus::values())],
            'base_subscription_plan' => ['required', Rule::in(array_map(
                static fn (SubscriptionPlan $plan): string => $plan->value,
                SubscriptionPlan::cases(),
            ))],
            'reports_subscription_enabled' => ['boolean'],
            'reports_customer_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ])->after(function ($validator) use ($normalized): void {
            if ($normalized['reports_subscription_enabled'] && $normalized['reports_customer_limit'] === null) {
                $validator->errors()->add('reports_customer_limit', 'Provide the reports customer limit when the add-on is enabled.');
            }

            if (
                $normalized['account_type'] === AccountType::BOTH->value
                && $normalized['base_subscription_plan'] === SubscriptionPlan::BASIC->value
            ) {
                $validator->errors()->add('base_subscription_plan', 'Retail + wholesale business accounts require a paid subscription.');
            }
        })->validate();

        if ($validated['account_type'] === AccountType::SUPPLIER->value) {
            $validated['wholesale_enabled'] = true;
        }

        if ($validated['account_type'] === AccountType::RETAILER->value) {
            $validated['retail_enabled'] = true;
        }

        if ($validated['account_type'] === AccountType::BOTH->value) {
            $validated['retail_enabled'] = true;
            $validated['wholesale_enabled'] = true;
        }

        if (! $validated['reports_subscription_enabled']) {
            $validated['reports_customer_limit'] = null;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, ?int $actorId = null, string $source = 'accounts_resource'): Account
    {
        $payload = $this->normalizePayload($input, true);

        /** @var Account $account */
        $account = DB::transaction(function () use ($payload, $actorId, $source): Account {
            $account = Account::query()->create([
                ...$payload,
                'created_by_user_id' => $actorId,
            ]);

            $this->syncActiveSubscription($account, $payload, $actorId, $source);

            return $account;
        });

        return $account->fresh(['subscriptions']) ?? $account;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Account $account, array $input, ?int $actorId = null, string $source = 'accounts_resource'): Account
    {
        $payload = $this->normalizePayload($input, false, $account->id);

        DB::transaction(function () use ($account, $payload, $actorId, $source): void {
            $account->fill($payload);
            $account->save();

            $this->syncActiveSubscription($account, $payload, $actorId, $source);
        });

        return $account->fresh(['subscriptions']) ?? $account;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncActiveSubscription(Account $account, array $payload, ?int $actorId = null, string $source = 'accounts_resource'): void
    {
        $subscription = $account->subscriptions()
            ->where('status', 'active')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            $account->subscriptions()->create([
                'plan_code' => $payload['base_subscription_plan'],
                'status' => 'active',
                'reports_enabled' => $payload['reports_subscription_enabled'],
                'reports_customer_limit' => $payload['reports_customer_limit'],
                'starts_at' => now(),
                'meta' => ['source' => $source],
                'created_by_user_id' => $actorId,
            ]);

            return;
        }

        $subscription->fill([
            'plan_code' => $payload['base_subscription_plan'],
            'reports_enabled' => $payload['reports_subscription_enabled'],
            'reports_customer_limit' => $payload['reports_customer_limit'],
            'meta' => array_merge($subscription->meta ?? [], ['source' => $source]),
        ]);
        $subscription->save();
    }
}
