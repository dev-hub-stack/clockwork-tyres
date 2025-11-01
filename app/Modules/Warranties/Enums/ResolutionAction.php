<?php

namespace App\Modules\Warranties\Enums;

enum ResolutionAction: string
{
    case REPLACE = 'replace';
    case REFUND = 'refund';
    case REPAIR = 'repair';
    case NO_ACTION = 'no_action';
    
    public function getLabel(): string
    {
        return match($this) {
            self::REPLACE => 'Replace Item',
            self::REFUND => 'Refund Customer',
            self::REPAIR => 'Repair/Fix',
            self::NO_ACTION => 'No Action Needed',
        };
    }
    
    public function getIcon(): string
    {
        return match($this) {
            self::REPLACE => 'heroicon-o-arrow-path',
            self::REFUND => 'heroicon-o-currency-dollar',
            self::REPAIR => 'heroicon-o-wrench',
            self::NO_ACTION => 'heroicon-o-minus-circle',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::REPLACE => 'warning',
            self::REFUND => 'success',
            self::REPAIR => 'info',
            self::NO_ACTION => 'gray',
        };
    }
}
