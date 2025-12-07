<?php

namespace App\Services;

use App\Modules\Products\Models\ProductModel;
use Illuminate\Support\Facades\Log;

/**
 * Model Lookup Service
 * 
 * Handles finding or creating models during order sync
 */
class ModelLookupService
{
    /**
     * Find or create a model by name
     * Note: In this CRM, models are NOT linked to brands
     * 
     * @param string $modelName
     * @return ProductModel
     */
    public function findOrCreate(string $modelName): ProductModel
    {
        // Normalize model name
        $modelName = trim($modelName);
        
        if (empty($modelName)) {
            throw new \InvalidArgumentException('Model name cannot be empty');
        }
        
        // Search for existing model (case-insensitive)
        $model = ProductModel::whereRaw('LOWER(name) = ?', [strtolower($modelName)])->first();
        
        if ($model) {
            Log::info('Model found', [
                'model_id' => $model->id,
                'model_name' => $model->name
            ]);
            return $model;
        }
        
        // Create new model
        $model = ProductModel::create([
            'name' => $modelName,
        ]);
        
        Log::info('Model created', [
            'model_id' => $model->id,
            'model_name' => $model->name
        ]);
        
        return $model;
    }
    
    /**
     * Find model by name (without creating)
     * 
     * @param string $modelName
     * @return ProductModel|null
     */
    public function find(string $modelName): ?ProductModel
    {
        $modelName = trim($modelName);
        
        if (empty($modelName)) {
            return null;
        }
        
        return ProductModel::whereRaw('LOWER(name) = ?', [strtolower($modelName)])->first();
    }
}
