<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\TaxSetting;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    // Cache duration in seconds (24 hours)
    protected const CACHE_TTL = 86400;

    // Cache keys
    protected const CACHE_KEY_TAX = 'settings.tax.default';
    protected const CACHE_KEY_CURRENCY = 'settings.currency.base';
    protected const CACHE_KEY_BRANDING = 'settings.branding.active';
    protected const CACHE_KEY_ALL_TAXES = 'settings.tax.all';
    protected const CACHE_KEY_ALL_CURRENCIES = 'settings.currency.all';

    /**
     * Get default tax setting (cached)
     */
    public function getDefaultTax(): ?TaxSetting
    {
        return Cache::remember(self::CACHE_KEY_TAX, self::CACHE_TTL, function () {
            return TaxSetting::getDefault();
        });
    }

    /**
     * Get all active tax settings (cached)
     */
    public function getAllTaxes()
    {
        return Cache::remember(self::CACHE_KEY_ALL_TAXES, self::CACHE_TTL, function () {
            return TaxSetting::active()->get();
        });
    }

    /**
     * Get base currency (cached)
     */
    public function getBaseCurrency(): ?CurrencySetting
    {
        return Cache::remember(self::CACHE_KEY_CURRENCY, self::CACHE_TTL, function () {
            return CurrencySetting::getBase();
        });
    }

    /**
     * Get all active currencies (cached)
     */
    public function getAllCurrencies()
    {
        return Cache::remember(self::CACHE_KEY_ALL_CURRENCIES, self::CACHE_TTL, function () {
            return CurrencySetting::active()->get();
        });
    }

    /**
     * Get active company branding (cached)
     */
    public function getCompanyBranding(): ?CompanyBranding
    {
        return Cache::remember(self::CACHE_KEY_BRANDING, self::CACHE_TTL, function () {
            return CompanyBranding::getActive();
        });
    }

    /**
     * Get tax rate percentage
     */
    public function getTaxRate(): float
    {
        $tax = $this->getDefaultTax();
        return $tax ? (float) $tax->rate : 0.00;
    }

    /**
     * Check if tax is inclusive by default
     */
    public function isTaxInclusiveDefault(): bool
    {
        $tax = $this->getDefaultTax();
        return $tax ? $tax->tax_inclusive_default : true;
    }

    /**
     * Get currency symbol
     */
    public function getCurrencySymbol(): string
    {
        $currency = $this->getBaseCurrency();
        return $currency ? $currency->currency_symbol : '$';
    }

    /**
     * Get currency code
     */
    public function getCurrencyCode(): string
    {
        $currency = $this->getBaseCurrency();
        return $currency ? $currency->currency_code : 'USD';
    }

    /**
     * Format amount with currency
     */
    public function formatCurrency(float $amount, ?CurrencySetting $currency = null): string
    {
        $currency = $currency ?? $this->getBaseCurrency();
        
        if (!$currency) {
            return '$' . number_format($amount, 2);
        }

        return $currency->format($amount);
    }

    /**
     * Get company name
     */
    public function getCompanyName(): string
    {
        $branding = $this->getCompanyBranding();
        return $branding ? $branding->company_name : 'Company Name';
    }

    /**
     * Get company logo URL
     */
    public function getCompanyLogo(): ?string
    {
        $branding = $this->getCompanyBranding();
        return $branding ? $branding->logo_url : null;
    }

    /**
     * Get company details array
     */
    public function getCompanyDetails(): array
    {
        $branding = $this->getCompanyBranding();
        return $branding ? $branding->full_details : [];
    }

    /**
     * Get document prefix
     */
    public function getDocumentPrefix(string $type): string
    {
        $branding = $this->getCompanyBranding();
        
        if (!$branding) {
            return match($type) {
                'invoice' => 'INV-',
                'quote' => 'QUO-',
                'order' => 'ORD-',
                'consignment' => 'CON-',
                default => 'DOC-',
            };
        }

        return match($type) {
            'invoice' => $branding->invoice_prefix,
            'quote' => $branding->quote_prefix,
            'order' => $branding->order_prefix,
            'consignment' => $branding->consignment_prefix,
            default => 'DOC-',
        };
    }

    /**
     * Get document footer text
     */
    public function getDocumentFooter(string $type): ?string
    {
        $branding = $this->getCompanyBranding();
        return $branding ? $branding->getFooterText($type) : null;
    }

    /**
     * Calculate tax amount
     */
    public function calculateTax(float $amount, bool $taxInclusive = null, ?float $taxRate = null): float
    {
        $rate = $taxRate ?? $this->getTaxRate();
        $inclusive = $taxInclusive ?? $this->isTaxInclusiveDefault();

        if ($inclusive) {
            // Tax is included in the amount, extract it
            return round($amount - ($amount / (1 + ($rate / 100))), 2);
        } else {
            // Tax is not included, calculate on top
            return round($amount * ($rate / 100), 2);
        }
    }

    /**
     * Calculate amount with tax
     */
    public function calculateAmountWithTax(float $amount, bool $taxInclusive = null, ?float $taxRate = null): float
    {
        $rate = $taxRate ?? $this->getTaxRate();
        $inclusive = $taxInclusive ?? $this->isTaxInclusiveDefault();

        if ($inclusive) {
            // Amount already includes tax
            return $amount;
        } else {
            // Add tax to amount
            return round($amount * (1 + ($rate / 100)), 2);
        }
    }

    /**
     * Calculate amount without tax
     */
    public function calculateAmountWithoutTax(float $amount, bool $taxInclusive = null, ?float $taxRate = null): float
    {
        $rate = $taxRate ?? $this->getTaxRate();
        $inclusive = $taxInclusive ?? $this->isTaxInclusiveDefault();

        if ($inclusive) {
            // Remove tax from amount
            return round($amount / (1 + ($rate / 100)), 2);
        } else {
            // Amount is already without tax
            return $amount;
        }
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_TAX);
        Cache::forget(self::CACHE_KEY_CURRENCY);
        Cache::forget(self::CACHE_KEY_BRANDING);
        Cache::forget(self::CACHE_KEY_ALL_TAXES);
        Cache::forget(self::CACHE_KEY_ALL_CURRENCIES);
    }

    /**
     * Clear specific cache
     */
    public function clearTaxCache(): void
    {
        Cache::forget(self::CACHE_KEY_TAX);
        Cache::forget(self::CACHE_KEY_ALL_TAXES);
    }

    public function clearCurrencyCache(): void
    {
        Cache::forget(self::CACHE_KEY_CURRENCY);
        Cache::forget(self::CACHE_KEY_ALL_CURRENCIES);
    }

    public function clearBrandingCache(): void
    {
        Cache::forget(self::CACHE_KEY_BRANDING);
    }
}
