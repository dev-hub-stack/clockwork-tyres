<?php

namespace App\Modules\Procurement\Actions;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Models\ProcurementSubmission;
use App\Modules\Procurement\Support\SupplierGroupedProcurementPlanner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitGroupedProcurementAction
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $meta
     */
    public function execute(
        Account $retailerAccount,
        User $actor,
        array $lineItems,
        ?Customer $customer = null,
        ?Order $sourceOrder = null,
        ?string $notes = null,
        array $meta = [],
    ): ProcurementSubmission {
        if (! $retailerAccount->isRetailer()) {
            throw new InvalidArgumentException('Procurement submissions must be created from a retailer-capable account.');
        }

        if ($customer instanceof Customer && (int) $customer->account_id !== (int) $retailerAccount->id) {
            throw new InvalidArgumentException('The selected customer does not belong to the active retailer account.');
        }

        $plan = SupplierGroupedProcurementPlanner::plan($lineItems);

        if (($plan['supplier_count'] ?? 0) < 1) {
            throw new InvalidArgumentException('At least one approved supplier group is required before submitting procurement.');
        }

        return DB::transaction(function () use ($retailerAccount, $actor, $customer, $sourceOrder, $notes, $meta, $plan): ProcurementSubmission {
            $currency = isset($meta['currency']) && is_string($meta['currency']) && $meta['currency'] !== ''
                ? strtoupper($meta['currency'])
                : 'AED';

            $submission = ProcurementSubmission::create([
                'retailer_account_id' => $retailerAccount->id,
                'submitted_by_user_id' => $actor->id,
                'status' => ProcurementWorkflowStage::SUBMITTED,
                'supplier_count' => (int) ($plan['supplier_count'] ?? 0),
                'request_count' => (int) ($plan['supplier_count'] ?? 0),
                'line_item_count' => (int) ($plan['line_item_count'] ?? 0),
                'quantity_total' => (int) ($plan['quantity_total'] ?? 0),
                'subtotal' => (float) ($plan['subtotal'] ?? 0),
                'currency' => $currency,
                'source' => 'admin_workbench',
                'meta' => array_merge($meta, [
                    'plan' => $plan,
                    'source_customer_id' => $customer?->id,
                    'source_customer_name' => $customer?->name,
                    'source_order_id' => $sourceOrder?->id,
                    'notes' => $notes,
                ]),
                'submitted_at' => now(),
            ]);

            $submission->forceFill([
                'submission_number' => sprintf('PRB-%s-%04d', now()->format('Ymd'), $submission->id),
            ])->save();

            foreach ($plan['supplier_orders'] ?? [] as $supplierOrder) {
                $supplierId = $supplierOrder['supplier_id'] ?? null;

                if (! is_numeric($supplierId)) {
                    throw new InvalidArgumentException('Each grouped supplier order must include a supplier account id.');
                }

                $connection = AccountConnection::query()
                    ->approved()
                    ->where('retailer_account_id', $retailerAccount->id)
                    ->where('supplier_account_id', (int) $supplierId)
                    ->first();

                if (! $connection instanceof AccountConnection) {
                    throw new InvalidArgumentException('Procurement can only be submitted to approved supplier connections.');
                }

                $supplierCustomer = $this->resolveSupplierCustomer($connection, $retailerAccount);

                $request = ProcurementRequest::create([
                    'procurement_submission_id' => $submission->id,
                    'retailer_account_id' => $retailerAccount->id,
                    'supplier_account_id' => (int) $supplierId,
                    'account_connection_id' => $connection->id,
                    'customer_id' => $supplierCustomer->id,
                    'submitted_by_user_id' => $actor->id,
                    'current_stage' => ProcurementWorkflowStage::SUBMITTED,
                    'line_item_count' => count($supplierOrder['line_items'] ?? []),
                    'quantity_total' => (int) ($supplierOrder['quantity_total'] ?? 0),
                    'subtotal' => (float) ($supplierOrder['subtotal'] ?? 0),
                    'currency' => $currency,
                    'notes' => $notes,
                    'submitted_at' => now(),
                    'meta' => [
                        'supplier_order' => $supplierOrder,
                        'action' => $supplierOrder['action'] ?? null,
                        'source_customer_id' => $customer?->id,
                        'source_customer_name' => $customer?->name,
                        'source_order_id' => $sourceOrder?->id,
                    ],
                ]);

                $request->forceFill([
                    'request_number' => sprintf('PRQ-%s-%04d', now()->format('Ymd'), $request->id),
                ])->save();

                foreach ($supplierOrder['line_items'] ?? [] as $lineItem) {
                    $request->items()->create([
                        'sku' => $lineItem['sku'] ?? null,
                        'product_name' => $lineItem['product_name'] ?? 'Procurement item',
                        'size' => $lineItem['size'] ?? null,
                        'quantity' => (int) ($lineItem['quantity'] ?? 0),
                        'unit_price' => (float) ($lineItem['unit_price'] ?? 0),
                        'line_total' => (float) ($lineItem['line_total'] ?? 0),
                        'source' => $lineItem['source'] ?? null,
                        'status' => $lineItem['status'] ?? null,
                        'note' => $lineItem['note'] ?? null,
                        'payload' => $lineItem,
                    ]);
                }

                $quote = $this->createSupplierQuote(
                    customer: $supplierCustomer,
                    request: $request,
                    supplierOrder: $supplierOrder,
                    sourceOrder: $sourceOrder,
                    notes: $notes,
                    currency: $currency,
                );

                $request->forceFill([
                    'quote_order_id' => $quote->id,
                    'meta' => array_merge($request->meta ?? [], [
                        'linked_quote_id' => $quote->id,
                        'linked_quote_number' => $quote->quote_number,
                        'linked_quote_order_number' => $quote->order_number,
                    ]),
                ])->save();
            }

            return $submission->fresh(['requests.items', 'retailerAccount', 'submittedBy']);
        });
    }

    private function resolveSupplierCustomer(AccountConnection $connection, Account $retailerAccount): Customer
    {
        $connection->loadMissing('supplierCustomer');

        if ($connection->supplierCustomer instanceof Customer) {
            return $connection->supplierCustomer;
        }

        $customer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => $retailerAccount->name,
            'email' => null,
            'account_id' => $connection->supplier_account_id,
            'external_source' => 'account_connection',
            'external_customer_id' => (string) $retailerAccount->id,
            'status' => 'active',
        ]);

        $connection->forceFill([
            'supplier_customer_id' => $customer->id,
        ])->save();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $supplierOrder
     */
    private function createSupplierQuote(
        Customer $customer,
        ProcurementRequest $request,
        array $supplierOrder,
        ?Order $sourceOrder,
        ?string $notes,
        string $currency,
    ): Order {
        $items = array_map(function (array $lineItem): array {
            $item = [
                'quantity' => max(1, (int) ($lineItem['quantity'] ?? 1)),
                'unit_price' => round((float) ($lineItem['unit_price'] ?? 0), 2),
                'tax_inclusive' => (bool) ($lineItem['tax_inclusive'] ?? true),
                'discount' => round((float) ($lineItem['discount'] ?? 0), 2),
                'sku' => $lineItem['sku'] ?? null,
                'product_name' => $lineItem['product_name'] ?? 'Procurement item',
                'product_description' => $lineItem['product_description'] ?? null,
                'warehouse_id' => isset($lineItem['warehouse_id']) && is_numeric($lineItem['warehouse_id'])
                    ? (int) $lineItem['warehouse_id']
                    : null,
                'item_attributes' => array_filter([
                    'size' => $lineItem['size'] ?? null,
                    'source' => $lineItem['source'] ?? null,
                    'status' => $lineItem['status'] ?? null,
                    'note' => $lineItem['note'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ];

            if (isset($lineItem['product_variant_id']) && is_numeric($lineItem['product_variant_id'])) {
                $item['product_variant_id'] = (int) $lineItem['product_variant_id'];
            } elseif (isset($lineItem['product_id']) && is_numeric($lineItem['product_id'])) {
                $item['product_id'] = (int) $lineItem['product_id'];
            }

            return $item;
        }, $supplierOrder['line_items'] ?? []);

        $quote = $this->orderService->createOrder([
            'document_type' => DocumentType::QUOTE,
            'customer_id' => $customer->id,
            'channel' => 'wholesale',
            'external_source' => 'clockwork_procurement',
            'external_order_id' => $request->request_number,
            'currency' => $currency,
            'tax_inclusive' => $sourceOrder?->tax_inclusive ?? true,
            'vehicle_year' => $sourceOrder?->vehicle_year,
            'vehicle_make' => $sourceOrder?->vehicle_make,
            'vehicle_model' => $sourceOrder?->vehicle_model,
            'vehicle_sub_model' => $sourceOrder?->vehicle_sub_model,
            'order_notes' => $notes,
            'items' => $items,
        ]);

        $this->orderService->sendQuote($quote);

        return $quote->fresh(['items', 'customer']);
    }
}
