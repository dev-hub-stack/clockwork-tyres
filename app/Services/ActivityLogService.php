<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActivityLogService
{
    public static function log(
        string $action,
        string $description,
        ?Model $model = null,
        ?int $userId = null,
    ): ?ActivityLog {
        [$resolvedUserId, $resolvedCustomerId] = self::resolveActorIds($userId);

        if (! $resolvedUserId && ! $resolvedCustomerId) {
            return null;
        }

        return self::persist([
            'user_id' => $resolvedUserId,
            'customer_id' => $resolvedCustomerId,
            'action' => $action,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'description' => $description,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public static function logForCustomer(
        string $action,
        string $description,
        ?Model $model = null,
        ?int $customerId = null,
    ): ?ActivityLog {
        $resolvedCustomerId = self::resolveCustomerId($customerId);

        if (! $resolvedCustomerId) {
            return null;
        }

        return self::persist([
            'user_id' => null,
            'customer_id' => $resolvedCustomerId,
            'action' => $action,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'description' => $description,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function resolveActorIds(?int $userId): array
    {
        if ($userId !== null) {
            return [self::resolveUserId($userId), null];
        }

        $actor = auth()->user();

        if ($actor instanceof User && $actor->exists) {
            return [$actor->getKey(), null];
        }

        if ($actor instanceof Customer && $actor->exists) {
            return [null, $actor->getKey()];
        }

        $authId = auth()->id();

        if ($authId) {
            return [self::resolveUserId((int) $authId), null];
        }

        return [null, null];
    }

    private static function resolveUserId(?int $userId): ?int
    {
        if (! $userId) {
            return null;
        }

        return User::query()->whereKey($userId)->exists() ? $userId : null;
    }

    private static function resolveCustomerId(?int $customerId): ?int
    {
        if (! $customerId) {
            return null;
        }

        return Customer::query()->whereKey($customerId)->exists() ? $customerId : null;
    }

    private static function persist(array $attributes): ?ActivityLog
    {
        try {
            return ActivityLog::create($attributes);
        } catch (\Throwable $exception) {
            Log::warning('ActivityLogService: failed to persist activity log', [
                'action' => $attributes['action'] ?? null,
                'user_id' => $attributes['user_id'] ?? null,
                'customer_id' => $attributes['customer_id'] ?? null,
                'model_type' => $attributes['model_type'] ?? null,
                'model_id' => $attributes['model_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}