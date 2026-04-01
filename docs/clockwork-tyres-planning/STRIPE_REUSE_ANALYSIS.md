# Stripe Reuse Analysis

## Short Answer

We should reuse the **subscription model ideas** from legacy Clockwork, but we should **not** reuse the old Stripe implementation directly.

## What Exists in Legacy Clockwork

### Registration and plan selection

- The legacy frontend combined login and business registration in [login.component.html](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/login/login.component.html).
- During registration, the retailer flow submitted a `plan` field through [login.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/login/login.component.ts) to `POST /api/dealer`.
- Supplier registration used a separate `vendorRegister()` path from [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts).

### Actual Stripe subscription checkout

- The old registration page did **not** perform Stripe subscription checkout itself.
- Actual subscription purchase happened later from the plans page in [plans.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/my-accounts/plans/plans.component.ts).
- That page redirected to the backend route `plans/{slug}/subscription/checkout`.
- The backend subscription flow lived in [PlanController.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Controllers/Api/PlanController.php).

### Feature gating and plan enforcement

- Legacy plan/business rules were handled through:
  - [Plan.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Models/Plan.php)
  - [PlanFeature.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Models/PlanFeature.php)
  - [PlanService.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Services/PlanService.php)
  - [CheckDealerPlanLimits.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Middleware/CheckDealerPlanLimits.php)

## What We Should Reuse

- The idea of **feature-based entitlements** instead of hardcoded role checks.
- The idea of a **single plan summary** that can drive both UI visibility and API enforcement.
- The concept of a **subscription-managed premium tier** instead of manual plan toggles everywhere.
- The legacy understanding that registration and subscription checkout are **separate steps**.

## What We Should Not Reuse

### Do not reuse the old Stripe payment implementation

- The old frontend order payment flow used `ngx-stripe` token/card handling in [stripe-payment.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/stripe-payment/stripe-payment.component.ts).
- The current backend wholesale payment flow still uses `\Stripe\Charge::create()` in [StripePaymentGateway.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Services/Wholesale/StripePaymentGateway.php).
- For Clockwork Tyres subscriptions, this is not the direction to extend.

### Do not reuse the old plan schema as-is

- Legacy plans depend on `stripe_plan` and old plan slug assumptions in [PlanController.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Controllers/Api/PlanController.php) and [Plan.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Models/Plan.php).
- Clockwork Tyres now has account-level subscription groundwork in [AccountSubscription.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Modules/Accounts/Models/AccountSubscription.php).
- George’s new rules are also different from the old starter/premium model.

### Do not reuse legacy dealer/vendor registration semantics

- The current public registration endpoints in [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/routes/api-wholesale.php) still point to inquiry-style handlers.
- [DealerController.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Http/Controllers/Wholesale/DealerController.php) explicitly treats `POST /dealer` and `POST /vendor` as intake requests, not real account creation.
- For Clockwork Tyres, we need a new business-account registration flow on top of the new account model.

## Recommended Direction for Clockwork Tyres

### Registration

- Keep the storefront registration page as the public onboarding entry point.
- Model registration around **one business account** with mode:
  - `retailer`
  - `supplier`
  - `both`
- Keep subscription choice as part of onboarding UI, but treat actual billing activation as a clean backend process.

### Billing

- Use the new account subscription foundation in [AccountSubscription.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Modules/Accounts/Models/AccountSubscription.php).
- Build the Tyres subscription layer around:
  - one main subscription per business account
  - reports add-on as a separate configurable entitlement
  - super-admin controlled add-on limits based on connected wholesale customers

### Stripe implementation recommendation

- For new subscription billing, use Stripe Billing + Checkout Sessions.
- Do not extend the legacy card-token flow for subscriptions.
- Keep order payments separate from subscriptions, especially because George confirmed retail/wholesale transactions are offline for now.

## Practical Conclusion

Use legacy Clockwork for:

- registration UX inspiration
- entitlement concepts
- plan gating patterns

Do not use legacy Clockwork for:

- direct Stripe payment implementation
- old plan schema
- old dealer/vendor account assumptions
