# Clockwork Tyres Launch Hardening Checklist

Date: April 4, 2026

This checklist is the practical pre-launch hardening path for the current Clockwork Tyres build.

## Command Audit

Run the backend launch-readiness audit before any release candidate sign-off:

```powershell
php artisan clockwork:launch-readiness --storefront-path="C:\Users\Dell\Documents\Gerorge\clockwork-tyres-storefront"
```

Optional machine-readable output:

```powershell
php artisan clockwork:launch-readiness --storefront-path="C:\Users\Dell\Documents\Gerorge\clockwork-tyres-storefront" --json
```

The audit currently checks:

- application key exists
- database connectivity works
- no pending migrations remain
- S3 image storage is configured
- backend Vite asset manifest exists
- runtime cache directories are writable
- critical admin routes are registered
- queue driver is not `sync` for production readiness
- storefront production build artifact exists when the storefront path is supplied

## Backend Release Checks

1. `php artisan optimize:clear`
2. `php artisan migrate --force`
3. `php artisan view:cache`
4. `php artisan config:cache`
5. `php artisan route:cache`
6. Run the focused governance/platform regression:

```powershell
php artisan test tests/Unit/Accounts/SuperAdminOverviewDataTest.php tests/Unit/Accounts/BusinessAccountInsightsTest.php tests/Feature/AccountResourceTest.php tests/Feature/DashboardPageTest.php tests/Feature/SuperAdminOperationalAccessTest.php
```

7. Run the procurement/admin workflow regression:

```powershell
php artisan test tests/Feature/ProcurementWorkbenchTest.php tests/Feature/AdminProcurementCheckoutEntryPointsTest.php tests/Feature/SupplierNetworkPagesTest.php tests/Feature/ProcurementRequestResourceTest.php
```

## Storefront Release Checks

1. `npm run build`
2. `npm run test`
3. `npm run test:e2e`
4. Confirm the deployed storefront is login-gated for counter use
5. Confirm pricing cards match George’s plan language:
   - Retailers: `Starter / Plus / Enterprise`
   - Wholesalers: `Starter / Premium / Enterprise`

## Environment Notes

- Payments remain out of scope for phase 1.
- Super admin is a platform bird’s-eye role, not a business operations role.
- Warehouses and inventory are scoped to the active business account.
- Product, addon, and tyre images all rely on S3-backed storage.

## Exit Criteria

The release candidate is considered ready for pilot/UAT when:

- launch-readiness audit has no failures
- critical backend/storefront regressions are green
- seeded or pilot accounts can complete the login -> catalog -> cart -> checkout -> order view flow
- admin can complete supplier discovery -> procurement -> quote -> invoice flow
- super admin can view platform metrics and business-account summaries without seeing business-ops modules
