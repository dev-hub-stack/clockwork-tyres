<?php

namespace App\Services;

use App\Modules\Products\Models\Finish;
use Illuminate\Support\Facades\Log;

/**
 * Finish Lookup Service
 * 
 * Handles finding or creating finishes during order sync
 */
class FinishLookupService
{
    /**
     * Find or create a finish by name
     * 
     * @param string $finishName
     * @return Finish
     */
    public function findOrCreate(string $finishName): Finish
    {
        // Normalize finish name
        $finishName = trim($finishName);
        
        if (empty($finishName)) {
            throw new \InvalidArgumentException('Finish name cannot be empty');
        }
        
        // Search for existing finish (case-insensitive)
        // Note: finishes table uses 'finish' column name
        $finish = Finish::whereRaw('LOWER(finish) = ?', [strtolower($finishName)])->first();
        
        if ($finish) {
            Log::info('Finish found', [
                'finish_id' => $finish->id,
                'finish_name' => $finish->finish
            ]);
            return $finish;
        }
        
        // Create new finish
        $finish = Finish::create([
            'finish' => $finishName,
        ]);
        
        Log::info('Finish created', [
            'finish_id' => $finish->id,
            'finish_name' => $finish->finish
        ]);
        
        return $finish;
    }
    
    /**
     * Find finish by name (without creating)
     * 
     * @param string $finishName
     * @return Finish|null
     */
    public function find(string $finishName): ?Finish
    {
        $finishName = trim($finishName);
        
        if (empty($finishName)) {
            return null;
        }
        
        return Finish::whereRaw('LOWER(finish) = ?', [strtolower($finishName)])->first();
    }
}
