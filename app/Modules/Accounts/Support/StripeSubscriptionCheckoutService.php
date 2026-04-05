<?php

namespace App\Modules\Accounts\Support;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Models\SubscriptionPlanCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Subscription;

class StripeSubscriptionCheckoutService
{
    public function createCheckoutSession(
        Account $account,
        AccountSubscription $subscription,
        SubscriptionPlanCatalog $planCatalog
    ): array {
        if (! $planCatalog->requiresStripeCheckout()) {
            throw new RuntimeException('The selected plan does not require Stripe checkout.');
        }

        $this->configureStripe();

        $stripeCustomerId = $subscription->stripe_customer_id ?: $this->ensureStripeCustomer($account);
        $stripePriceId = $this->ensureStripePrice($planCatalog);

        $session = Session::create([
            'mode' => 'subscription',
            'customer' => $stripeCustomerId,
            'client_reference_id' => (string) $subscription->id,
            'line_items' => [[
                'price' => $stripePriceId,
                'quantity' => 1,
            ]],
            'payment_method_collection' => 'if_required',
            'success_url' => route('wholesale.auth.business-register.billing.success', [], true)
                .'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->frontendRegisterUrl([
                'billing' => 'cancelled',
                'resume_token' => $subscription->billing_resume_token,
                'email' => $account->createdBy?->email,
                'mode' => $account->account_type?->value,
                'plan' => $subscription->plan_code?->value,
            ]),
            'subscription_data' => [
                'trial_period_days' => $planCatalog->trial_days,
                'metadata' => $this->metadataFor($account, $subscription),
            ],
            'metadata' => $this->metadataFor($account, $subscription),
        ]);

        $subscription->forceFill([
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_checkout_session_id' => $session->id,
            'meta' => array_merge($subscription->meta ?? [], [
                'billing_provider' => 'stripe',
                'stripe_price_id' => $stripePriceId,
            ]),
        ])->save();

        return [
            'requires_checkout' => true,
            'billing_mode' => $planCatalog->billing_mode,
            'status' => $subscription->status,
            'resume_token' => $subscription->billing_resume_token,
            'plan_display_name' => $planCatalog->display_name,
            'trial_days' => $planCatalog->trial_days,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    public function resumeCheckout(AccountSubscription $subscription): array
    {
        $subscription->loadMissing('account.createdBy');

        return $this->createCheckoutSession(
            $subscription->account,
            $subscription,
            app(SubscriptionPlanCatalogResolver::class)->for(
                $subscription->account->account_type->value,
                $subscription->plan_code->value,
            ),
        );
    }

    public function syncCheckoutSession(string $sessionId): AccountSubscription
    {
        $this->configureStripe();

        /** @var Session $session */
        $session = Session::retrieve([
            'id' => $sessionId,
            'expand' => ['subscription', 'customer'],
        ]);

        $subscription = AccountSubscription::query()
            ->where('stripe_checkout_session_id', $session->id)
            ->orWhere('id', (int) $session->client_reference_id)
            ->firstOrFail();

        if ($session->customer) {
            $subscription->stripe_customer_id = is_string($session->customer)
                ? $session->customer
                : $session->customer->id;
        }

        if ($session->subscription) {
            $this->syncStripeSubscription(
                is_string($session->subscription)
                    ? Subscription::retrieve($session->subscription)
                    : $session->subscription,
                $subscription,
            );
        } else {
            $subscription->save();
        }

        return $subscription->fresh();
    }

    public function syncStripeSubscription(Subscription $stripeSubscription, ?AccountSubscription $subscription = null): ?AccountSubscription
    {
        $subscription ??= AccountSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscription->id)
            ->orWhere('id', (int) Arr::get($stripeSubscription->metadata, 'account_subscription_id'))
            ->first();

        if (! $subscription instanceof AccountSubscription) {
            return null;
        }

        $startsAt = $stripeSubscription->current_period_start
            ? CarbonImmutable::createFromTimestamp($stripeSubscription->current_period_start)
            : now();
        $trialEndsAt = $stripeSubscription->trial_end
            ? CarbonImmutable::createFromTimestamp($stripeSubscription->trial_end)
            : null;
        $endsAt = $stripeSubscription->ended_at
            ? CarbonImmutable::createFromTimestamp($stripeSubscription->ended_at)
            : null;

        $subscription->forceFill([
            'status' => (string) $stripeSubscription->status,
            'starts_at' => $subscription->starts_at ?? $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'stripe_customer_id' => is_string($stripeSubscription->customer)
                ? $stripeSubscription->customer
                : $subscription->stripe_customer_id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'meta' => array_merge($subscription->meta ?? [], [
                'billing_provider' => 'stripe',
                'stripe_status' => $stripeSubscription->status,
                'current_period_start' => $stripeSubscription->current_period_start,
                'current_period_end' => $stripeSubscription->current_period_end,
            ]),
        ])->save();

        return $subscription->fresh();
    }

    private function configureStripe(): void
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        Stripe::setApiKey($secret);
        Stripe::setApiVersion('2026-02-25.clover');
    }

    private function ensureStripeCustomer(Account $account): string
    {
        $owner = $account->createdBy ?: User::query()->find($account->created_by_user_id);

        /** @var Customer $customer */
        $customer = Customer::create([
            'name' => $account->name,
            'email' => $owner?->email,
            'metadata' => [
                'account_id' => (string) $account->id,
                'account_type' => $account->account_type?->value,
            ],
        ]);

        return $customer->id;
    }

    private function ensureStripePrice(SubscriptionPlanCatalog $planCatalog): string
    {
        if ($planCatalog->stripe_price_id) {
            return $planCatalog->stripe_price_id;
        }

        $stripeProductId = $planCatalog->stripe_product_id;

        if (! $stripeProductId) {
            /** @var Product $product */
            $product = Product::create([
                'name' => sprintf('Clockwork Tyres %s %s', ucfirst($planCatalog->account_mode), $planCatalog->display_name),
                'metadata' => [
                    'account_mode' => $planCatalog->account_mode,
                    'plan_code' => $planCatalog->plan_code,
                ],
            ]);

            $stripeProductId = $product->id;
        }

        /** @var Price $price */
        $price = Price::create([
            'currency' => strtolower($planCatalog->currency ?: 'AED'),
            'unit_amount' => $planCatalog->amount_minor,
            'recurring' => [
                'interval' => $planCatalog->billing_interval ?: 'month',
            ],
            'product' => $stripeProductId,
            'metadata' => [
                'account_mode' => $planCatalog->account_mode,
                'plan_code' => $planCatalog->plan_code,
            ],
        ]);

        $planCatalog->forceFill([
            'stripe_product_id' => $stripeProductId,
            'stripe_price_id' => $price->id,
        ])->save();

        return $price->id;
    }

    /**
     * @return array<string, string>
     */
    private function metadataFor(Account $account, AccountSubscription $subscription): array
    {
        return [
            'account_id' => (string) $account->id,
            'account_subscription_id' => (string) $subscription->id,
            'billing_resume_token' => (string) $subscription->billing_resume_token,
            'account_mode' => (string) $account->account_type?->value,
            'plan_code' => (string) $subscription->plan_code?->value,
        ];
    }

    /**
     * @param  array<string, string|null>  $query
     */
    private function frontendRegisterUrl(array $query = []): string
    {
        $base = rtrim((string) config('services.wholesale.frontend_url', 'http://localhost:4200'), '/');
        $url = $base.'/register';
        $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));

        return $queryString !== '' ? $url.'?'.$queryString : $url;
    }
}
