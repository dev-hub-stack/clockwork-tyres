<?php

namespace App\Filament\Pages;

use App\Modules\Procurement\Support\ProcurementWorkflow;
use App\Modules\Procurement\Support\SupplierGroupedProcurementPlanner;
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

    public array $supplierGroups = [];

    public array $placeOrderCallout = [];

    public array $requestSummary = [];

    public array $plannedSubmission = [];

    public function mount(): void
    {
        $this->plannedSubmission = SupplierGroupedProcurementPlanner::plan($this->buildWorkbenchLineItems());
        $this->statusRail = $this->buildStatusRail();
        $this->supplierGroups = $this->buildSupplierGroups();
        $this->placeOrderCallout = $this->buildPlaceOrderCallout();
        $this->requestSummary = $this->buildRequestSummary();
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

    protected function buildRequestSummary(): array
    {
        return [
            [
                'label' => 'Supplier groups',
                'value' => $this->plannedSubmission['supplier_count'] ?? 0,
                'note' => 'Each supplier gets its own grouped workbench section.',
            ],
            [
                'label' => 'Line items',
                'value' => $this->plannedSubmission['line_item_count'] ?? 0,
                'note' => 'Products can be added from multiple suppliers into one submission.',
            ],
            [
                'label' => 'Submit action',
                'value' => $this->plannedSubmission['place_order_label'] ?? 'Place Order',
                'note' => 'The backend fans out to separate supplier orders automatically.',
            ],
        ];
    }

    protected function buildSupplierGroups(): array
    {
        return array_map(
            static function (array $supplierOrder, int $index): array {
                return [
                    'supplier_name' => $supplierOrder['supplier_name'],
                    'supplier_reference' => 'Supplier order #' . ($index + 1),
                    'status' => 'Draft',
                    'summary' => 'Separate supplier group that will be submitted in the same unified action.',
                    'items' => array_map(
                        static fn (array $lineItem): array => [
                            'sku' => $lineItem['sku'] ?? '--',
                            'product_name' => $lineItem['product_name'] ?? 'Procurement line',
                            'size' => $lineItem['size'] ?? 'Pending tyre size',
                            'quantity' => $lineItem['quantity'] ?? 0,
                            'source' => 'Manual supplier selection',
                            'status' => 'Ready',
                            'note' => $lineItem['note'] ?? 'Grouped under the selected supplier before submit.',
                        ],
                        $supplierOrder['line_items'] ?? []
                    ),
                ];
            },
            $this->plannedSubmission['supplier_orders'] ?? [],
            array_keys($this->plannedSubmission['supplier_orders'] ?? [])
        );
    }

    protected function buildPlaceOrderCallout(): array
    {
        return [
            'title' => 'One place order, split per supplier behind the scenes',
            'description' => 'Retailer admins can add products from multiple suppliers into one workbench. Each supplier stays grouped, but the final submit action can place everything together in one click.',
            'highlights' => [
                'Supplier groups stay separate in the cart.',
                'The retailer still completes one unified submit action.',
                'The backend creates separate supplier orders, quotes, and invoices per supplier.',
            ],
            'action_label' => $this->plannedSubmission['place_order_label'] ?? 'Place Order',
            'supporting_note' => 'This keeps the admin flow fast without collapsing supplier ownership.',
        ];
    }

    protected function buildWorkbenchLineItems(): array
    {
        return [
            [
                'sku' => 'TYR-NT-001',
                'product_name' => 'Premium touring tyre',
                'size' => '225/45R17',
                'quantity' => 4,
                'supplier_id' => 101,
                'supplier_name' => 'North Coast Tyres',
                'unit_price' => 520.00,
                'note' => 'Own stock first, then supplier-backed fulfilment.',
            ],
            [
                'sku' => 'TYR-NT-002',
                'product_name' => 'SUV all-season tyre',
                'size' => '235/55R18',
                'quantity' => 2,
                'supplier_id' => 101,
                'supplier_name' => 'North Coast Tyres',
                'unit_price' => 610.00,
                'note' => 'Grouped under the same supplier before submit.',
            ],
            [
                'sku' => 'TYR-DL-011',
                'product_name' => 'Performance tyre',
                'size' => '245/40R18',
                'quantity' => 6,
                'supplier_id' => 202,
                'supplier_name' => 'Desert Line Trading',
                'unit_price' => 480.00,
                'note' => 'This group is isolated for separate supplier workflow.',
            ],
            [
                'sku' => 'TYR-DL-022',
                'product_name' => 'Utility tyre',
                'size' => '215/65R16',
                'quantity' => 8,
                'supplier_id' => 202,
                'supplier_name' => 'Desert Line Trading',
                'unit_price' => 410.00,
                'note' => 'The retailer still places one unified workbench order.',
            ],
        ];
    }
}
