<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Accounts\Actions\CreateBusinessAccountRegistrationAction;
use App\Modules\Accounts\Support\StripeSubscriptionCheckoutService;
use App\Modules\Accounts\Support\SubscriptionPlanCatalogResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessRegistrationController extends BaseWholesaleController
{
    public function __construct(
        private readonly CreateBusinessAccountRegistrationAction $createRegistration,
        private readonly SubscriptionPlanCatalogResolver $planCatalogResolver,
        private readonly StripeSubscriptionCheckoutService $checkoutService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'country' => ['required', 'string', 'max:120'],
            'account_mode' => ['required', Rule::in(['retailer', 'supplier', 'both'])],
            'plan_preference' => ['required', Rule::in(['basic', 'premium'])],
            'accepts_terms' => ['required', 'accepted'],
            'accepts_privacy' => ['required', 'accepted'],
            'registration_source' => ['nullable', 'string', 'max:120'],
            'trade_license' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $registration = $this->createRegistration->execute([
            ...$validated,
            'supporting_document' => $request->file('trade_license'),
        ]);

        $billing = $registration['billing'] ?? [
            'requires_checkout' => false,
        ];
        $message = 'Business account created successfully. You can continue with Clockwork setup.';

        if (($billing['requires_checkout'] ?? false) === true) {
            $account = Account::query()->findOrFail((int) $registration['account']['id']);
            $subscription = AccountSubscription::query()->findOrFail((int) $registration['subscription']['id']);
            $planCatalog = $this->planCatalogResolver->for(
                (string) $account->account_type?->value,
                (string) $subscription->plan_code?->value,
            );

            try {
                $billing = $this->checkoutService->createCheckoutSession($account, $subscription, $planCatalog);
                $message = sprintf(
                    'Business account created successfully. Continue to Stripe to start your %d-day free trial.',
                    (int) ($billing['trial_days'] ?? 14),
                );
            } catch (\Throwable $exception) {
                report($exception);

                $billing = array_merge($billing, [
                    'status' => $subscription->status,
                    'checkout_url' => null,
                    'session_id' => null,
                ]);
                $message = 'Business account created successfully, but we could not start subscription checkout automatically. Use the resume checkout option to continue your trial.';
            }
        }

        return $this->success(
            [
                ...$registration,
                'billing' => $billing,
            ],
            $message,
            201
        );
    }

    public function resumeCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resume_token' => ['required', 'string'],
        ]);

        $subscription = AccountSubscription::query()
            ->where('billing_resume_token', (string) $validated['resume_token'])
            ->firstOrFail();

        if ($subscription->isBillingLive()) {
            return $this->success([
                'billing' => [
                    'requires_checkout' => false,
                    'status' => $subscription->status,
                    'resume_token' => $subscription->billing_resume_token,
                    'checkout_url' => null,
                ],
            ], 'Your trial is already active.');
        }

        $billing = $this->checkoutService->resumeCheckout($subscription);

        return $this->success([
            'billing' => $billing,
        ], 'Redirecting you back to Stripe to continue the trial signup.');
    }

    public function checkoutSuccess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        try {
            $subscription = $this->checkoutService->syncCheckoutSession((string) $validated['session_id']);

            return redirect()->away($this->loginRedirectUrl([
                'registered' => '1',
                'billing' => 'trial-started',
                'email' => $subscription->account->createdBy?->email,
            ]));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->away($this->loginRedirectUrl([
                'billing' => 'sync-failed',
            ]));
        }
    }

    /**
     * @param  array<string, string|null>  $query
     */
    private function loginRedirectUrl(array $query = []): string
    {
        $base = rtrim((string) config('services.wholesale.frontend_url', 'http://localhost:4200'), '/');
        $url = $base.'/login';
        $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));

        return $queryString !== '' ? $url.'?'.$queryString : $url;
    }
}
