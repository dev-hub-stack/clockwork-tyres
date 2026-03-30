<?php

namespace App\Modules\Products\Enums;

enum TyreImportBatchStatus: string
{
    case STAGED = 'staged';
    case INVALID_HEADERS = 'invalid_headers';
    case EMPTY = 'empty';

    public function label(): string
    {
        return match ($this) {
            self::STAGED => 'Staged',
            self::INVALID_HEADERS => 'Invalid Headers',
            self::EMPTY => 'Empty',
        };
    }
}
