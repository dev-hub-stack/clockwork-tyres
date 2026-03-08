<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CompanyBranding extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company_branding';

    protected $fillable = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'tax_registration_number',
        'commercial_registration',
        'logo_path',
        'primary_color',
        'secondary_color',
        'invoice_prefix',
        'quote_prefix',
        'order_prefix',
        'consignment_prefix',
        'invoice_footer',
        'quote_footer',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Boot method to ensure only one active branding
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($branding) {
            if ($branding->is_active) {
                // Deactivate all other branding records
                static::where('id', '!=', $branding->id)
                    ->update(['is_active' => false]);
            }
        });
    }

    /**
     * Get the active company branding
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Get logo URL — served via S3/CloudFront CDN for emails and browsers.
     * New uploads go to S3; falls back to public disk for pre-migration files.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($this->logo_path, 'http')) {
            return $this->logo_path;
        }

        // Primary: S3 disk — Storage::disk('s3')->url() returns the CloudFront CDN URL
        // because AWS_URL is set to S3IMAGES_URL (CloudFront) in .env
        if (Storage::disk('s3')->exists($this->logo_path)) {
            return Storage::disk('s3')->url($this->logo_path);
        }

        // Fallback: file may be on local public disk (uploaded before S3 migration)
        if (Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        // Last resort: build CloudFront URL directly without an exists() check
        $cdnUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
        if ($cdnUrl) {
            return $cdnUrl . '/' . ltrim($this->logo_path, '/');
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Get full company details for documents
     */
    public function getFullDetailsAttribute(): array
    {
        return [
            'name' => $this->company_name,
            'address' => $this->company_address,
            'phone' => $this->company_phone,
            'email' => $this->company_email,
            'website' => $this->company_website,
            'tax_number' => $this->tax_registration_number,
            'cr_number' => $this->commercial_registration,
            'logo_url' => $this->logo_url,
        ];
    }

    /**
     * Generate next document number with prefix
     */
    public function getNextDocumentNumber(string $type): string
    {
        $prefix = match($type) {
            'invoice' => $this->invoice_prefix,
            'quote' => $this->quote_prefix,
            'order' => $this->order_prefix,
            'consignment' => $this->consignment_prefix,
            default => 'DOC-',
        };

        // This will be enhanced later with actual counter logic
        return $prefix . str_pad(1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get document footer text
     */
    public function getFooterText(string $type): ?string
    {
        return match($type) {
            'invoice' => $this->invoice_footer,
            'quote' => $this->quote_footer,
            default => null,
        };
    }
}
