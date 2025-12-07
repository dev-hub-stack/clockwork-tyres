<?php

namespace App\Services;

use App\Modules\Products\Models\Brand;
use Illuminate\Support\Facades\Log;

/**
 * Brand Lookup Service
 * 
 * Handles finding or creating brands during order sync
 */
class BrandLookupService
{
    /**
     * Find or create a brand by name
     * 
     * @param string $brandName
     * @return Brand
     */
    public function findOrCreate(string $brandName): Brand
    {
        // Normalize brand name (trim whitespace)
        $brandName = trim($brandName);
        
        if (empty($brandName)) {
            throw new \InvalidArgumentException('Brand name cannot be empty');
        }
        
        // Search for existing brand (case-insensitive)
        $brand = Brand::whereRaw('LOWER(name) = ?', [strtolower($brandName)])->first();
        
        if ($brand) {
            Log::info('Brand found', [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name
            ]);
            return $brand;
        }
        
        // Create new brand
        $brand = Brand::create([
            'name' => $brandName,
            'status' => true, // Active by default
        ]);
        
        Log::info('Brand created', [
            'brand_id' => $brand->id,
            'brand_name' => $brand->name
        ]);
        
        return $brand;
    }
    
    /**
     * Find brand by name (without creating)
     * 
     * @param string $brandName
     * @return Brand|null
     */
    public function find(string $brandName): ?Brand
    {
        $brandName = trim($brandName);
        
        if (empty($brandName)) {
            return null;
        }
        
        return Brand::whereRaw('LOWER(name) = ?', [strtolower($brandName)])->first();
    }
}
