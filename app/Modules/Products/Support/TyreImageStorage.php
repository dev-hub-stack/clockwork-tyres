<?php

namespace App\Modules\Products\Support;

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
}
