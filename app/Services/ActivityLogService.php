<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?int $userId = null,
    ): ?ActivityLog {
        $resolvedUserId = $userId ?? auth()->id();

        if (! $resolvedUserId) {
            return null;
        }

        return ActivityLog::create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'description' => $description,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}