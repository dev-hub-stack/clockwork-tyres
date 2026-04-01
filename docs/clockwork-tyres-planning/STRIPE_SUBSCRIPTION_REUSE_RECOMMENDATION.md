# Stripe Subscription Reuse Recommendation

## Decision

Reuse the **subscription concepts and entitlement rules**, but do **not** reuse the legacy Stripe implementation as-is.

## Reuse

- **Plan entitlement thinking** from [PlanService.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Services/PlanService.php)
  - The idea of feature-based access limits is still valid.
  - This maps well to Clockwork Tyres rules like:
    - retailer basic vs premium
    - supplier basic vs premium
    - reports add-on limits by connected wholesale customers

- **Feature gate enforcement pattern** from [CheckDealerPlanLimits.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Middleware/CheckDealerPlanLimits.php)
  - The middleware/service split is a good pattern.
  - We should reimplement it against `accounts`, entitlements, and account capabilities, not dealers.

- **Frontend subscription visibility pattern** from [login.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/login/login.component.ts) and [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)
  - Showing plan options during onboarding is reusable as a UX idea.
  - The old combined login/register page is useful as visual and flow reference.

- **New account-scoped subscription model** in [AccountSubscription.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Modules/Accounts/Models/AccountSubscription.php)
  - This should be the new canonical billing record.
  - It already matches the new platform direction better than dealer-bound legacy subscriptions.

## Do Not Reuse Directly

- **Legacy dealer-centric subscription controller flow** in [PlanController.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Controllers/Api/PlanController.php)
  - It is tightly coupled to `Dealer`, old plan slugs, and the previous product rules.
  - It assumes the old starter/premium model, not George's new retailer/supplier/both model.

- **Legacy `stripe_plan` / plan-slug model** in [PlanController.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Http/Controllers/Api/PlanController.php) and [PlanService.php](C:/Users/Dell/Documents/Gerorge/tunerstop-vendor/app/Services/PlanService.php)
  - For new Stripe work, we should store **Stripe price IDs**, not rely on the old `stripe_plan` naming and old plan assumptions.

- **Current order-payment Stripe code** in [StripePaymentGateway.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Services/Wholesale/StripePaymentGateway.php)
  - This uses the legacy Charges API style for order authorization/capture.
  - It is for wholesale order payments, not recurring subscriptions.
  - George already said retail and wholesale transactions are offline for now, so this is not the right base for subscription billing.

- **Current webhook handling** in [StripeWebhookController.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Http/Controllers/Webhook/StripeWebhookController.php)
  - It only handles `charge.captured`.
  - Subscription billing needs a different webhook set and account-subscription synchronization logic.

## Why

- The **business rules changed**:
  - one business account can be retailer, supplier, or both
  - one main subscription
  - reports as an add-on
  - supplier and retailer limits differ

- The **data model changed**:
  - subscriptions now belong to an account, not a dealer record

- The **Stripe integration shape should change**:
  - use Stripe Billing for recurring subscriptions
  - use Checkout Sessions for subscription checkout
  - use account-scoped webhook syncing
  - keep order-payment Stripe logic separate from subscription billing

## Recommended New Billing Shape

- Subscription owner:
  - `Account`

- Main persisted model:
  - `AccountSubscription`

- Stripe primitives:
  - Checkout Sessions for subscription signup/change
  - Stripe Billing prices for plan pricing
  - Customer Portal for self-service plan/payment management later

- Webhooks to implement later:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `invoice.paid`
  - `invoice.payment_failed`

## Practical Conclusion

- **Reuse the rules, not the old Stripe code.**
- **Reuse the onboarding UI direction, not the old dealer/vendor billing contract.**
- **Build the new subscription slice around `AccountSubscription` and the new account model.**
