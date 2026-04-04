<?php

namespace App\Modules\Products\Support;

use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class TyreImageStorage
{
    public const FOLDER = 'tyres';

    public static function normalizeImportPath(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $value = ltrim($value, '/');

        if (preg_match('/^(products|addons|tyres)\//i', $value) === 1) {
            return $value;
        }

        return self::FOLDER.'/'.$value;
    }

    public static function url(?string $value): ?string
    {
        $normalized = self::normalizeImportPath($value);

        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        $cdnUrl = config('filesystems.disks.s3.url');

        if (! is_string($cdnUrl) || trim($cdnUrl) === '') {
            return null;
        }

        return rtrim($cdnUrl, '/').'/'.ltrim($normalized, '/');
    }

    public static function isExternalUrl(?string $value): bool
    {
        return is_string($value)
            && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'));
    }

    public static function isManagedPath(?string $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        if (self::isExternalUrl($value)) {
            return false;
        }

        return str_starts_with(ltrim($value, '/'), self::FOLDER.'/');
    }

    public static function deleteManagedPath(?string $value): void
    {
        if (! self::isManagedPath($value)) {
            return;
        }

        Storage::disk('s3')->delete(ltrim($value, '/'));
    }

    public static function storeUploadedFile(TyreAccountOffer $offer, UploadedFile $file, string $slot): string
    {
        $offer->loadMissing(['account', 'tyreCatalogGroup']);

        $accountSlug = Str::slug($offer->account?->slug ?: ($offer->account?->name ?: 'account'));
        $brand = Str::slug($offer->tyreCatalogGroup?->brand_name ?: 'brand');
        $model = Str::slug($offer->tyreCatalogGroup?->model_name ?: 'model');
        $fullSize = Str::slug($offer->tyreCatalogGroup?->full_size ?: 'size');
        $sku = Str::slug($offer->source_sku ?: 'offer-'.$offer->getKey());
        $slotSlug = Str::slug(str_replace('_', '-', $slot));
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = $slotSlug.'-'.Str::uuid().'.'.$extension;
        $path = implode('/', [
            self::FOLDER,
            $accountSlug,
            $brand,
            $model,
            $fullSize,
            $sku,
            $filename,
        ]);

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), 'public');

        return $path;
    }
}
