<?php

namespace App\Modules\Products\Support;

final class TyreGridLayout
{
    /**
     * Build the pqGrid column shape from George's tyre sample sheet while
     * preserving the existing CRM grid behaviour.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        $columns = [
            self::textColumn('sku', 'SKU', 170, true),
            self::textColumn('brand', 'Brand', 140),
            self::textColumn('model', 'Model', 180),
            self::textColumn('full_size', 'Full Size', 140),
            self::numberColumn('width', 'Width', 95),
            self::numberColumn('height', 'Height', 95),
            self::numberColumn('rim_size', 'Rim Size', 95),
            self::textColumn('load_index', 'Load Index', 110),
            self::textColumn('speed_rating', 'Speed Rating', 120),
            self::textColumn('dot', 'DOT', 95),
            self::textColumn('country', 'Country', 120),
            self::textColumn('type', 'Type', 130),
            self::booleanColumn('runflat', 'Runflat', 95),
            self::booleanColumn('rfid', 'RFID', 90),
            self::textColumn('sidewall', 'Sidewall', 120),
            self::textColumn('warranty', 'Warranty', 120),
        ];

        foreach (TyreCatalogContract::blueprint()['pricing_columns'] ?? [] as $field => $definition) {
            $columns[] = self::numberColumn(
                $field,
                (string) ($definition['label'] ?? ucfirst(str_replace('_', ' ', $field))),
                130
            );
        }

        $columns[] = self::textColumn('brand_image', 'Brand Image', 180);
        $columns[] = self::textColumn('product_image_1', 'Product Image 1', 180);
        $columns[] = self::textColumn('product_image_2', 'Product Image 2', 180);
        $columns[] = self::textColumn('product_image_3', 'Product Image 3', 180);

        return $columns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function toolbarActions(): array
    {
        return [
            [
                'id' => 'refresh-grid',
                'label' => 'Reload Preview',
                'variant' => 'secondary',
                'icon' => 'bi bi-arrow-clockwise',
                'disabled' => false,
                'hint' => 'Reload the latest staged tyre preview for the active account.',
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

    /**
     * @return array<string, mixed>
     */
    private static function booleanColumn(string $dataIndx, string $title, int $width): array
    {
        return [
            'title' => $title,
            'dataIndx' => $dataIndx,
            'dataType' => 'string',
            'width' => $width,
            'editable' => false,
            'filter' => [
                'type' => 'textbox',
                'condition' => 'contain',
                'listeners' => ['timeout' => 250],
            ],
        ];
    }
}
