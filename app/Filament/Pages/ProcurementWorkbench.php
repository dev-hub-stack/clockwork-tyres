<?php

namespace App\Filament\Pages;

use App\Modules\Procurement\Support\ProcurementWorkflow;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class ProcurementWorkbench extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Procurement Workbench';

    protected static ?string $title = 'Procurement Workbench';

    protected static UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'procurement-workbench';

    protected string $view = 'filament.pages.procurement-workbench';

    public array $statusRail = [];

    public array $requestCart = [];

    public array $supplierOptions = [];

    public function mount(): void
    {
        $this->statusRail = $this->buildStatusRail();
        $this->requestCart = $this->buildRequestCart();
        $this->supplierOptions = $this->buildSupplierOptions();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_quotes') ?? false;
    }

    protected function buildStatusRail(): array
    {
        $descriptions = [
            'draft' => 'Build the request cart and confirm the tyre lines.',
            'submitted' => 'Send the procurement request forward to the supplier side.',
            'supplier_review' => 'Supplier reviews availability, pricing, and fulfilment fit.',
            'quoted' => 'Review the supplier quote before approval.',
            'approved' => 'Approved requests move toward invoice conversion.',
            'invoiced' => 'Invoice creation follows the quote approval path.',
            'stock_reserved' => 'Reserved stock is held against the selected supplier source.',
            'stock_deducted' => 'Stock deduction follows the current CRM method.',
            'fulfilled' => 'Procurement is completed and ready for downstream reporting.',
            'cancelled' => 'Cancelled requests add stock back to the selected warehouse.',
        ];

        return array_map(
            static function (array $stage, int $index) use ($descriptions): array {
                $value = $stage['value'];

                return [
                    'key' => $value,
                    'label' => $stage['label'],
                    'description' => $descriptions[$value] ?? 'Procurement stage placeholder.',
                    'state' => $index === 0 ? 'active' : 'pending',
                    'terminal' => $stage['terminal'],
                ];
            },
            ProcurementWorkflow::stages(),
            array_keys(ProcurementWorkflow::stages())
        );
    }

    protected function buildRequestCart(): array
    {
        return [
            [
                'sku' => 'TYR-REQ-001',
                'product_name' => 'Request cart placeholder',
                'size' => 'Pending tyre sheet',
                'quantity' => 4,
                'selected_supplier' => 'Select supplier',
                'status' => 'Draft',
                'note' => 'Tyre procurement lines will be mapped once the launch sheet is shared.',
            ],
            [
                'sku' => 'TYR-REQ-002',
                'product_name' => 'Request cart placeholder',
                'size' => 'Pending tyre sheet',
                'quantity' => 8,
                'selected_supplier' => 'Select supplier',
                'status' => 'Draft',
                'note' => 'Supplier selection stays manual for this launch.',
            ],
        ];
    }

    protected function buildSupplierOptions(): array
    {
        return [
            [
                'label' => 'Approved supplier placeholder',
                'value' => null,
                'help' => 'Supplier selection will be wired after procurement rules are finalized.',
            ],
            [
                'label' => 'Secondary supplier placeholder',
                'value' => null,
                'help' => 'This shell only reserves the UI slot for later supplier choice logic.',
            ],
        ];
    }
}
