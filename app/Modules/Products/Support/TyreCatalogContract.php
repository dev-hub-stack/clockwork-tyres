<?php

namespace App\Modules\Products\Support;

final class TyreCatalogContract
{
    public const VERSION = 1;

    public const CATEGORY = 'tyres';

    /**
     * George sample datasheet received on March 30, 2026.
     *
     * The contract below reflects the launch tyre upload shape exactly enough
     * for backend + storefront planning, while keeping importer persistence
     * work separate.
     *
     * @return array<string, mixed>
     */
    public static function blueprint(): array
    {
        return [
            'version' => self::VERSION,
            'category' => self::CATEGORY,
            'source_sheet' => [
                'file_name' => 'tyres-sample-datasheet.xlsx',
                'sheet_name' => 'Sheet1',
                'received_on' => '2026-03-30',
            ],
            'ingest_envelope' => [
                'source_file',
                'sheet_name',
                'row_number',
                'raw_row',
            ],
            'sections' => [
                'identity',
                'fitment',
                'attributes',
                'pricing',
                'media',
                'metadata',
                'audit',
            ],
            'pricing_levels' => [
                'retail',
                'wholesale_lvl1',
                'wholesale_lvl2',
                'wholesale_lvl3',
            ],
            'pricing_columns' => [
                'retail_price' => [
                    'label' => 'Retail',
                    'level' => 'retail',
                    'source_header' => 'Retail_price',
                ],
                'wholesale_price_lvl1' => [
                    'label' => 'Wholesale L1',
                    'level' => 'wholesale_lvl1',
                    'source_header' => 'wholesale_price_lvl1',
                ],
                'wholesale_price_lvl2' => [
                    'label' => 'Wholesale L2',
                    'level' => 'wholesale_lvl2',
                    'source_header' => 'wholesale_price_lvl2',
                ],
                'wholesale_price_lvl3' => [
                    'label' => 'Wholesale L3',
                    'level' => 'wholesale_lvl3',
                    'source_header' => 'wholesale_price_lvl3',
                ],
            ],
            'source_columns' => [
                ['source_header' => 'SKU', 'field' => 'sku', 'required' => true, 'section' => 'identity'],
                ['source_header' => 'Brand', 'field' => 'brand', 'required' => true, 'section' => 'identity'],
                ['source_header' => 'Model', 'field' => 'model', 'required' => true, 'section' => 'identity'],
                ['source_header' => 'width', 'field' => 'width', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'height', 'field' => 'height', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'rim_size', 'field' => 'rim_size', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'full_size', 'field' => 'full_size', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'load_index', 'field' => 'load_index', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'speed_rating', 'field' => 'speed_rating', 'required' => true, 'section' => 'fitment'],
                ['source_header' => 'DOT', 'field' => 'dot', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'Country', 'field' => 'country', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'Type', 'field' => 'type', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'Runflat', 'field' => 'runflat', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'RFID', 'field' => 'rfid', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'sidewall', 'field' => 'sidewall', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'warranty', 'field' => 'warranty', 'required' => false, 'section' => 'attributes'],
                ['source_header' => 'Retail_price', 'field' => 'retail_price', 'required' => true, 'section' => 'pricing'],
                ['source_header' => 'wholesale_price_lvl1', 'field' => 'wholesale_price_lvl1', 'required' => true, 'section' => 'pricing'],
                ['source_header' => 'wholesale_price_lvl2', 'field' => 'wholesale_price_lvl2', 'required' => true, 'section' => 'pricing'],
                ['source_header' => 'wholesale_price_lvl3', 'field' => 'wholesale_price_lvl3', 'required' => true, 'section' => 'pricing'],
                ['source_header' => 'brand_image', 'field' => 'brand_image', 'required' => false, 'section' => 'media'],
                ['source_header' => 'product_image_1', 'field' => 'product_image_1', 'required' => false, 'section' => 'media'],
                ['source_header' => 'product_image_2', 'field' => 'product_image_2', 'required' => false, 'section' => 'media'],
                ['source_header' => 'product_image_3', 'field' => 'product_image_3', 'required' => false, 'section' => 'media'],
            ],
            'required_fields' => [
                'sku',
                'brand',
                'model',
                'width',
                'height',
                'rim_size',
                'full_size',
                'load_index',
                'speed_rating',
                'retail_price',
                'wholesale_price_lvl1',
                'wholesale_price_lvl2',
                'wholesale_price_lvl3',
            ],
            'boolean_like_fields' => [
                'runflat',
                'rfid',
            ],
            'image_fields' => [
                'brand_image',
                'product_image_1',
                'product_image_2',
                'product_image_3',
            ],
            'grouping_rules' => [
                'storefront_merge_key' => [
                    'fields' => [
                        'brand',
                        'model',
                        'full_size',
                        'year',
                    ],
                    'field_sources' => [
                        'size' => 'full_size',
                        'year' => 'dot',
                    ],
                    'ignores' => [
                        'sku',
                    ],
                ],
            ],
            'launch_notes' => [
                'tyres are the launch category',
                'wheels remain a future category',
                'sample sheet received from George on March 30 2026',
                'import should normalize source headers into internal snake_case field names',
                'storefront grouping must ignore supplier SKU and merge by brand, model, size, and year',
            ],
            'validation_notes' => [
                'sample row full_size 245/35R20 does not match height value 30 and should be validated with George',
                'DOT sample value is 2026 and needs confirmation whether it is a year or full DOT code',
                'image columns currently look like file names and need storage-source confirmation',
            ],
        ];
    }
}
