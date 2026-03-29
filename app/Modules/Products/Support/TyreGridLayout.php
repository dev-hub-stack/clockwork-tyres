<?php

namespace App\Modules\Products\Support;

final class TyreGridLayout
{
    /**
     * Build the pqGrid column shape for the launch tyre scaffold.
     *
     * We intentionally keep this generic until the sample sheet arrives,
     * while still matching the existing CRM grid experience.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        $columns = [
            self::textColumn('sku', 'SKU', 170, true),
            self::textColumn('brand', 'Brand', 140),
            self::textColumn('pattern', 'Pattern', 180),
            self::textColumn('size', 'Size', 140),
            self::textColumn('load_index', 'Load Index', 110),
            self::textColumn('speed_rating', 'Speed Rating', 120),
        ];

        foreach (TyreCatalogContract::blueprint()['pricing_levels'] ?? [] as $pricingLevel) {
            $columns[] = self::numberColumn(
                $pricingLevel . '_price',
                self::pricingLevelLabel($pricingLevel),
                130
            );
        }

        $columns[] = self::textColumn('availability_note', 'Note', 220);

        return $columns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function toolbarActions(): array
    {
        return [
            [
                'id' => 'import-tyres',
                'label' => 'Import Tyres',
                'variant' => 'success',
                'icon' => 'bi bi-upload',
                'disabled' => true,
                'hint' => 'Enabled after George shares the final tyre sheet.',
            ],
            [
                'id' => 'refresh-grid',
                'label' => 'Refresh',
                'variant' => 'secondary',
                'icon' => 'bi bi-arrow-clockwise',
                'disabled' => false,
                'hint' => 'Reload placeholder scaffold rows.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textColumn(string $dataIndx, string $title, int $width, bool $frozen = false): array
    {
        return [
            'title' => $title,
            'dataIndx' => $dataIndx,
            'dataType' => 'string',
            'width' => $width,
            'editable' => false,
            'frozen' => $frozen,
            'filter' => [
                'type' => 'textbox',
                'condition' => 'contain',
                'listeners' => ['timeout' => 250],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function numberColumn(string $dataIndx, string $title, int $width): array
    {
        return [
            'title' => $title,
            'dataIndx' => $dataIndx,
            'dataType' => 'float',
            'width' => $width,
            'editable' => false,
            'format' => '#,##0.00',
            'filter' => [
                'type' => 'textbox',
                'condition' => 'begin',
                'listeners' => ['timeout' => 250],
            ],
        ];
    }

    private static function pricingLevelLabel(string $pricingLevel): string
    {
        return match ($pricingLevel) {
            'wholesale_lvl1' => 'Wholesale L1',
            'wholesale_lvl2' => 'Wholesale L2',
            'wholesale_lvl3' => 'Wholesale L3',
            default => ucfirst(str_replace('_', ' ', $pricingLevel)),
        };
    }
}
