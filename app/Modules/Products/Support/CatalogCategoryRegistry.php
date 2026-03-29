<?php

namespace App\Modules\Products\Support;

final class CatalogCategoryRegistry
{
    public const TYRES = 'tyres';

    public const WHEELS = 'wheels';

    /**
     * Returns the catalog categories we want to support in the new platform.
     *
     * Tyres are the launch category. Wheels stay represented so we can extend
     * them later without refactoring the catalog foundation again.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::TYRES => [
                'key' => self::TYRES,
                'label' => 'Tyres',
                'launch_status' => 'launch',
                'storefront_enabled' => true,
                'admin_grid' => 'tyre-grid',
                'search_modes' => ['vehicle', 'size'],
            ],
            self::WHEELS => [
                'key' => self::WHEELS,
                'label' => 'Wheels',
                'launch_status' => 'future',
                'storefront_enabled' => false,
                'admin_grid' => 'products-grid',
                'search_modes' => ['vehicle', 'size'],
            ],
        ];
    }

    public static function launchCategory(): string
    {
        return self::TYRES;
    }

    public static function enabledCategories(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $definition): bool => (bool) ($definition['storefront_enabled'] ?? false)
        ));
    }

    public static function isEnabled(string $category): bool
    {
        return (bool) (self::all()[$category]['storefront_enabled'] ?? false);
    }

    public static function isLaunchCategory(string $category): bool
    {
        return $category === self::launchCategory();
    }

    /**
     * @return array<int, string>
     */
    public static function searchModes(string $category): array
    {
        return self::all()[$category]['search_modes'] ?? [];
    }

    public static function definition(string $category): ?array
    {
        return self::all()[$category] ?? null;
    }
}
