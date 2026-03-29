<?php

namespace App\Modules\Products\Support;

final class TyreCatalogContract
{
    public const VERSION = 1;

    public const CATEGORY = 'tyres';

    /**
     * Keep the launch contract intentionally generic until the sample sheet arrives.
     *
     * @return array<string, array<int, string>|string|int>
     */
    public static function blueprint(): array
    {
        return [
            'version' => self::VERSION,
            'category' => self::CATEGORY,
            'ingest_envelope' => [
                'source_file',
                'sheet_name',
                'row_number',
                'raw_row',
            ],
            'sections' => [
                'identity',
                'merchandising',
                'pricing',
                'inventory',
                'fitment',
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
            'launch_notes' => [
                'tyres are the launch category',
                'wheels remain a future category',
                'final import fields will be mapped from George sample sheet',
            ],
        ];
    }
}
