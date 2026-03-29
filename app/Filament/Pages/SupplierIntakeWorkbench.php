<?php

namespace App\Filament\Pages;

use App\Modules\Procurement\Support\ProcurementWorkflow;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class SupplierIntakeWorkbench extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Supplier Intake';

    protected static ?string $title = 'Supplier Intake Workbench';

    protected static UnitEnum|string|null $navigationGroup = 'Procurement';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'supplier-intake-workbench';

    protected string $view = 'filament.pages.supplier-intake-workbench';

    public array $statusRail = [];
    public array $incomingRequests = [];
    public array $workflowNotes = [];
    public array $actionChecklist = [];

    public function mount(): void
    {
        $this->statusRail = $this->buildStatusRail();
        $this->incomingRequests = $this->buildIncomingRequests();
        $this->workflowNotes = $this->buildWorkflowNotes();
        $this->actionChecklist = $this->buildActionChecklist();
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
            'submitted' => 'Incoming procurement request arrives in the supplier portal.',
            'supplier_review' => 'Review stock, warehouse fit, and offered price level.',
            'quoted' => 'Send or confirm the supplier quote / proforma response.',
            'approved' => 'Retailer approves the quote from their admin side.',
            'invoiced' => 'Approved quote converts to invoice in the supplier workflow.',
            'stock_reserved' => 'Reserved stock is held against the selected warehouse.',
            'stock_deducted' => 'Stock deduction follows the current CRM behavior.',
            'fulfilled' => 'Supplier order is packed, shipped, or completed.',
            'cancelled' => 'Cancelled requests release inventory back to the selected warehouse.',
        ];

        $stageMap = array_values(array_filter(
            ProcurementWorkflow::stages(),
            static fn (array $stage): bool => $stage['value'] !== 'draft'
        ));

        return array_map(
            static function (array $stage, int $index) use ($descriptions): array {
                $value = $stage['value'];

                return [
                    'key' => $value,
                    'label' => $stage['label'],
                    'description' => $descriptions[$value] ?? 'Supplier intake stage placeholder.',
                    'state' => $index === 0 ? 'active' : 'pending',
                    'terminal' => $stage['terminal'],
                ];
            },
            $stageMap,
            array_keys($stageMap)
        );
    }

    protected function buildIncomingRequests(): array
    {
        return [
            [
                'request_number' => 'PROC-1001',
                'retailer' => 'Retailer placeholder',
                'sku' => 'TYR-SUP-001',
                'size' => 'Pending tyre sheet',
                'quantity' => 4,
                'ship_to' => 'Warehouse / ship-to placeholder',
                'po_number' => 'PO-PENDING-01',
                'status' => 'Submitted',
                'note' => 'Request arrives under Quotes & Proformas and is awaiting supplier review.',
            ],
            [
                'request_number' => 'PROC-1002',
                'retailer' => 'Retailer placeholder',
                'sku' => 'TYR-SUP-002',
                'size' => 'Pending tyre sheet',
                'quantity' => 8,
                'ship_to' => 'Warehouse / ship-to placeholder',
                'po_number' => 'PO-PENDING-02',
                'status' => 'Supplier Review',
                'note' => 'Supplier can review stock, choose the warehouse source, and prepare the quote.',
            ],
        ];
    }

    protected function buildWorkflowNotes(): array
    {
        return [
            [
                'title' => 'Quotes & Proformas inbox',
                'copy' => 'George described supplier intake as an inbox under Quotes & Proformas. New procurement requests should appear here first, not in the retail storefront flow.',
            ],
            [
                'title' => 'Invoice conversion',
                'copy' => 'Once the retailer approves the quote, the supplier-side flow converts it to an invoice using the same reporting CRM pattern we already have.',
            ],
            [
                'title' => 'View Store preview',
                'copy' => 'Supplier accounts also keep the read-only storefront preview mode so they can inspect how their products surface in the retail store without cart or checkout actions.',
            ],
        ];
    }

    protected function buildActionChecklist(): array
    {
        return [
            'Review the incoming procurement request in Quotes & Proformas.',
            'Confirm warehouse availability and selected price level.',
            'Respond with supplier quote or proforma.',
            'Convert approved quote to invoice.',
            'Reserve or deduct stock using the current CRM method.',
        ];
    }
}
