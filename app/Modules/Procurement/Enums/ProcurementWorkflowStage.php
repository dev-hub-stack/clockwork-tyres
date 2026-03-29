<?php

namespace App\Modules\Procurement\Enums;

enum ProcurementWorkflowStage: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case SUPPLIER_REVIEW = 'supplier_review';
    case QUOTED = 'quoted';
    case APPROVED = 'approved';
    case INVOICED = 'invoiced';
    case STOCK_RESERVED = 'stock_reserved';
    case STOCK_DEDUCTED = 'stock_deducted';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::SUPPLIER_REVIEW => 'Supplier Review',
            self::QUOTED => 'Quoted',
            self::APPROVED => 'Approved',
            self::INVOICED => 'Invoiced',
            self::STOCK_RESERVED => 'Stock Reserved',
            self::STOCK_DEDUCTED => 'Stock Deducted',
            self::FULFILLED => 'Fulfilled',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Ordered launch-stage list for admin pages.
     *
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::SUPPLIER_REVIEW,
            self::QUOTED,
            self::APPROVED,
            self::INVOICED,
            self::STOCK_RESERVED,
            self::STOCK_DEDUCTED,
            self::FULFILLED,
            self::CANCELLED,
        ];
    }

    /**
     * Stages that represent the procurement lifecycle before completion or cancellation.
     *
     * @return array<int, self>
     */
    public static function activeStages(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::SUPPLIER_REVIEW,
            self::QUOTED,
            self::APPROVED,
            self::INVOICED,
            self::STOCK_RESERVED,
            self::STOCK_DEDUCTED,
            self::FULFILLED,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::FULFILLED, self::CANCELLED], true);
    }

    public function isPreApproval(): bool
    {
        return in_array($this, [self::DRAFT, self::SUBMITTED, self::SUPPLIER_REVIEW, self::QUOTED], true);
    }

    public function isPostApproval(): bool
    {
        return in_array($this, [self::APPROVED, self::INVOICED, self::STOCK_RESERVED, self::STOCK_DEDUCTED, self::FULFILLED], true);
    }
}
