<?php

namespace App\Utility;

class Helper
{
    /**
     * Get full image URL from S3/CloudFront
     * 
     * @param string|null $imagePath
     * @return string
     */
    public static function getImagePath($imagePath)
    {
        if (empty($imagePath)) {
            // Return a data URL placeholder instead of asset path
            return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Image%3C/text%3E%3C/svg%3E';
        }
        
        // CloudFront URL (hardcoded like Tunerstop - same CDN)
        $cloudFrontUrl = 'https://d2iosncs8hpu1u.cloudfront.net/';
        
        // Clean up the image path
        $imagePath = ltrim($imagePath, '/');
        
        // If it already starts with http, return as is
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        
        // Add products/ prefix if not present
        if (!str_starts_with($imagePath, 'products/')) {
            $imagePath = 'products/' . $imagePath;
        }
        
        // Construct full CloudFront URL
        return $cloudFrontUrl . $imagePath;
    }

    /**
     * Get image thumbnail URL (Tunerstop pattern)
     * 
     * @param string|null $imagePath
     * @param int $width
     * @param int $height
     * @return string
     */
    public static function getImageThumbnailPath($imagePath, $width = 100, $height = 100)
    {
        if (empty($imagePath)) {
            return asset('images/placeholder.png');
        }
        
        // For now, return the full image path
        // TODO: Implement image resizing service or use CloudFront image transformation
        return self::getImagePath($imagePath);
    }

    /**
     * Get product image URL with proper path formatting
     * 
     * @param string|null $imagePath
     * @return string
     */
    public static function getProductImageUrl($imagePath)
    {
        if (empty($imagePath)) {
            return asset('images/no-image.png');
        }
        
        // If it's already a full URL, return it
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        
        // Tunerstop pattern: images are stored without 'products/' prefix in database
        // The full path is constructed when displaying
        return self::getImagePath($imagePath);
    }

    /**
     * Check if image exists in S3
     * 
     * @param string $imagePath
     * @return bool
     */
    public static function imageExists($imagePath)
    {
        try {
            return \Storage::disk('s3')->exists($imagePath);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format file size in human-readable format
     * 
     * @param int $bytes
     * @return string
     */
    public static function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
