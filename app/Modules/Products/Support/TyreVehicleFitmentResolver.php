<?php

namespace App\Modules\Products\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class TyreVehicleFitmentResolver
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   requested: bool,
     *   resolved: bool,
     *   filters: array<string, mixed>
     * }
     */
    public function resolve(array $filters): array
    {
        $vehicleSearch = $this->normalizeVehicleSearch($filters);

        if ($vehicleSearch === []) {
            return [
                'requested' => false,
                'resolved' => false,
                'filters' => [],
            ];
        }

        $metadata = [
            'vehicle_requested' => true,
            'vehicle_resolved' => false,
            'vehicle_source' => 'wheel-size',
            'vehicle_search' => $vehicleSearch,
        ];

        $apiKey = config('wheel_size.key') ?: env('WHEEL_SIZE_API_KEY');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return [
                'requested' => true,
                'resolved' => false,
                'filters' => $metadata,
            ];
        }

        $apiUrl = rtrim((string) config('wheel_size.url', env('WHEEL_SIZE_API_URL', 'https://api.wheel-size.com/v2/')), '/').'/';

        try {
            $response = Http::timeout(10)->get($apiUrl.'search/by_model/', array_merge($vehicleSearch, [
                'user_key' => trim($apiKey),
            ]));
        } catch (\Throwable) {
            return [
                'requested' => true,
                'resolved' => false,
                'filters' => $metadata,
            ];
        }

        if (! $response->successful()) {
            return [
                'requested' => true,
                'resolved' => false,
                'filters' => $metadata,
            ];
        }

        $resolvedFilters = $this->extractFitmentFilters($response->json());

        if ($resolvedFilters === []) {
            return [
                'requested' => true,
                'resolved' => false,
                'filters' => $metadata,
            ];
        }

        return [
            'requested' => true,
            'resolved' => true,
            'filters' => array_merge($metadata, [
                'vehicle_resolved' => true,
            ], $resolvedFilters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function normalizeVehicleSearch(array $filters): array
    {
        $searchRequested = $this->isTruthy($filters['searchByVehicle'] ?? null)
            || $this->isTruthy($filters['search_by_vehicle'] ?? null)
            || (
                is_string($filters['make'] ?? null)
                && is_string($filters['model'] ?? null)
                && is_string($filters['year'] ?? null)
            );

        if (! $searchRequested) {
            return [];
        }

        $vehicleSearch = [];

        foreach (['make', 'model', 'year'] as $field) {
            $value = $filters[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                return [];
            }

            $vehicleSearch[$field] = trim($value);
        }

        $modification = $filters['modification']
            ?? $filters['variant']
            ?? $filters['subModel']
            ?? $filters['sub_model']
            ?? null;

        if (is_string($modification) && trim($modification) !== '') {
            $vehicleSearch['modification'] = trim($modification);
        }

        return $vehicleSearch;
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    private function extractFitmentFilters(mixed $payload): array
    {
        $results = data_get($payload, 'data', data_get($payload, 'results', $payload));

        if (! is_array($results)) {
            return [];
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $fitment = $this->preferredFitment($result);

            if (! is_array($fitment)) {
                continue;
            }

            $filters = $this->fitmentFilters($fitment);

            if ($filters !== []) {
                return $filters;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    private function preferredFitment(array $result): ?array
    {
        $wheels = collect($result['wheels'] ?? []);

        if ($wheels->isEmpty()) {
            return null;
        }

        $selectedWheel = $wheels->first(function (mixed $wheel): bool {
            return is_array($wheel) && ($wheel['is_stock'] ?? false) === true;
        });

        if (! is_array($selectedWheel)) {
            $selectedWheel = $wheels->first();
        }

        if (! is_array($selectedWheel)) {
            return null;
        }

        if (is_array($selectedWheel['front'] ?? null)) {
            return $selectedWheel['front'];
        }

        return $selectedWheel;
    }

    /**
     * @param  array<string, mixed>  $fitment
     * @return array<string, mixed>
     */
    private function fitmentFilters(array $fitment): array
    {
        $sizeParts = $this->parseSizeString(
            $this->firstString([
                $fitment['tire_size'] ?? null,
                $fitment['tyre_size'] ?? null,
                $fitment['front_tire_size'] ?? null,
                $fitment['size'] ?? null,
            ])
        );

        $width = $this->firstInteger([
            $fitment['tire_width'] ?? null,
            $fitment['tyre_width'] ?? null,
            $fitment['section_width'] ?? null,
            $sizeParts['width'] ?? null,
        ]);

        $height = $this->firstInteger([
            $fitment['tire_aspect_ratio'] ?? null,
            $fitment['tyre_aspect_ratio'] ?? null,
            $fitment['aspect_ratio'] ?? null,
            $sizeParts['height'] ?? null,
        ]);

        $rimSize = $this->firstInteger([
            $fitment['rim_diameter'] ?? null,
            $fitment['rim_size'] ?? null,
            $sizeParts['rim_size'] ?? null,
        ]);

        if ($width === null || $height === null || $rimSize === null) {
            return [];
        }

        $filters = [
            'width' => $width,
            'height' => $height,
            'rim_size' => $rimSize,
        ];

        $loadIndex = $this->firstString([
            $fitment['load_index'] ?? null,
            $fitment['tire_load_index'] ?? null,
            $fitment['tyre_load_index'] ?? null,
            $sizeParts['load_index'] ?? null,
        ]);

        if ($loadIndex !== null) {
            $filters['load_index'] = $loadIndex;
        }

        $speedRating = $this->firstString([
            $fitment['speed_rating'] ?? null,
            $fitment['speed_index'] ?? null,
            $fitment['tire_speed_rating'] ?? null,
            $fitment['tyre_speed_rating'] ?? null,
            $sizeParts['speed_rating'] ?? null,
        ]);

        if ($speedRating !== null) {
            $filters['speed_rating'] = strtoupper($speedRating);
        }

        return $filters;
    }

    /**
     * @return array{width?: int, height?: int, rim_size?: int, load_index?: string, speed_rating?: string}
     */
    private function parseSizeString(?string $size): array
    {
        if (! is_string($size) || trim($size) === '') {
            return [];
        }

        $size = trim($size);

        if (! preg_match(
            '/(?P<width>\d{3})\s*\/\s*(?P<height>\d{2,3})\s*(?:ZR|R)\s*(?P<rim>\d{2})(?:\s+(?P<load>\d{2,3})(?P<speed>[A-Z]))?/i',
            $size,
            $matches
        )) {
            return [];
        }

        $parsed = [
            'width' => (int) $matches['width'],
            'height' => (int) $matches['height'],
            'rim_size' => (int) $matches['rim'],
        ];

        if (($matches['load'] ?? '') !== '') {
            $parsed['load_index'] = $matches['load'];
        }

        if (($matches['speed'] ?? '') !== '') {
            $parsed['speed_rating'] = strtoupper($matches['speed']);
        }

        return $parsed;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstInteger(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return false;
    }
}
