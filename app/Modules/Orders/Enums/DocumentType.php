<?php

namespace App\Modules\Orders\Enums;

enum DocumentType: string
{
    case QUOTE = 'quote';
    case INVOICE = 'invoice';
    case ORDER = 'order';

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match($this) {
            self::QUOTE => 'Quote',
            self::INVOICE => 'Invoice',
            self::ORDER => 'Order',
        };
    }

    /**
     * Get color for badges
     */
    public function color(): string
    {
        return match($this) {
            self::QUOTE => 'warning',
            self::INVOICE => 'info',
            self::ORDER => 'success',
        };
    }

    /**
     * Get icon for display
     */
    public function icon(): string
    {
        return match($this) {
            self::QUOTE => 'heroicon-o-document-text',
            self::INVOICE => 'heroicon-o-document-currency-dollar',
            self::ORDER => 'heroicon-o-shopping-bag',
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all options for select dropdown
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->label()];
        })->toArray();
    }
}
