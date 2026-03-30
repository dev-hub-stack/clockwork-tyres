<?php

namespace App\Modules\Products\Enums;

enum TyreImportRowStatus: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case DUPLICATE = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::VALID => 'Valid',
            self::INVALID => 'Invalid',
            self::DUPLICATE => 'Duplicate',
        };
    }
}
