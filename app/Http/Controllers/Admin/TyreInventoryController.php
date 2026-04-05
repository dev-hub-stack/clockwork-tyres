<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Support\AccountEntitlements;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Actions\UpsertTyreOfferInventoryAction;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\SyncTyreOfferInventoryStatus;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TyreInventoryController extends Controller
{
    public function __construct(
        private readonly UpsertTyreOfferInventoryAction $upsertInventory,
        private readonly SyncTyreOfferInventoryStatus $statusSync,
    ) {
    }

    private function currentAccount(?Request $request = null)
    {
        $request ??= request();

        if (! $request || ! auth()->check()) {
            return null;
        }

        return app(CurrentAccountResolver::class)
            ->resolve($request, auth()->user())
            ->currentAccount;
    }

    private function currentAccountId(?Request $request = null): ?int
    {
        return $this->currentAccount($request)?->id;
    }

    private function ensureInventoryEntitlement(Request $request): void
    {
        $account = $this->currentAccount($request);

        if (! $account || ! AccountEntitlements::for($account)->canManageOwnProductsAndInventory()) {
            throw new \RuntimeException('The current business account cannot manage its own tyre inventory on the active plan.');
        }
    }

    /**
     * @return array<int, int>
     */
    private function warehouseIdsForCurrentAccount(Request $request): array
    {
        $currentAccountId = $this->currentAccountId($request);

        if (! $currentAccountId) {
            return [];
        }

        return Warehouse::query()
            ->where('account_id', $currentAccountId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function saveBatch(Request $request)
    {
        ob_start();

        try {
            error_reporting(E_ERROR | E_PARSE);
            ini_set('display_errors', '0');
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            ini_set('max_input_vars', '10000');

            ob_clean();

            $this->ensureInventoryEntitlement($request);

            $changes = $request->input('list', []);

            if (empty($changes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No changes to save',
                    'addList' => [],
                    'updateList' => [],
                    'deleteList' => [],
                ]);
            }

            DB::beginTransaction();

            $updateList = $changes['updateList'] ?? [];
            $addList = $changes['addList'] ?? [];
            $deleteList = $changes['deleteList'] ?? [];

            $updatedRows = [];
            $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);
            $currentAccountId = $this->currentAccountId($request);

            foreach ($updateList as $row) {
                if (isset($row['id'])) {
                    $offer = TyreAccountOffer::query()
                        ->where('account_id', $currentAccountId)
                        ->find($row['id']);

                    if ($offer) {
                        foreach ($row as $key => $value) {
                            if (preg_match('/^qty(\d+)$/', $key, $matches)) {
                                $this->updateInventory($offer, (int) $matches[1], 'quantity', $value, $allowedWarehouseIds);
                            } elseif (preg_match('/^eta(\d+)$/', $key, $matches)) {
                                $this->updateInventory($offer, (int) $matches[1], 'eta', $value, $allowedWarehouseIds);
                            } elseif (preg_match('/^e_ta_q_ty(\d+)$/', $key, $matches)) {
                                $this->updateInventory($offer, (int) $matches[1], 'eta_qty', $value, $allowedWarehouseIds);
                            }
                        }
                    }
                }

                $updatedRows[] = $row;
            }

            DB::commit();
            ob_clean();

            return response()->json([
                'success' => true,
                'message' => 'Tyre inventory updated successfully',
                'addList' => $addList,
                'updateList' => $updatedRows,
                'deleteList' => $deleteList,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ob_clean();
            Log::error('Tyre inventory save batch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'errors' => [$e->getMessage()],
                'addList' => [],
                'updateList' => [],
                'deleteList' => [],
            ], 500);
        } finally {
            if (ob_get_length()) {
                ob_end_clean();
            }
        }
    }

    protected function updateInventory(
        TyreAccountOffer $offer,
        int $warehouseId,
        string $field,
        $value,
        array $allowedWarehouseIds = [],
    ): void {
        if (! empty($allowedWarehouseIds) && ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            throw new \RuntimeException('The selected warehouse is not available to the current business account.');
        }

        $inventory = TyreOfferInventory::query()->firstOrNew([
            'tyre_account_offer_id' => $offer->id,
            'warehouse_id' => $warehouseId,
        ]);

        $inventory->account_id = $offer->account_id;
        $inventory->quantity = $inventory->quantity ?? 0;
        $inventory->eta_qty = $inventory->eta_qty ?? 0;

        $normalizedValue = $this->normalizeInventoryValue($field, $value);
        $oldValue = $inventory->{$field} ?? null;

        if ($oldValue != $normalizedValue) {
            $inventory->{$field} = $normalizedValue;
            $inventory->save();

            $quantityBefore = (int) (($field === 'quantity' ? $oldValue : $inventory->quantity) ?? 0);
            $quantityAfter = (int) (($field === 'quantity' ? $normalizedValue : $inventory->quantity) ?? 0);

            InventoryLog::create([
                'warehouse_id' => $warehouseId,
                'tyre_account_offer_id' => $offer->id,
                'action' => InventoryLog::ACTION_ADJUSTMENT,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'quantity_change' => $field === 'quantity' ? ($quantityAfter - $quantityBefore) : 0,
                'eta_before' => $field === 'eta' ? $oldValue : $inventory->eta,
                'eta_after' => $field === 'eta' ? $normalizedValue : $inventory->eta,
                'eta_qty_before' => (int) (($field === 'eta_qty' ? $oldValue : $inventory->eta_qty) ?? 0),
                'eta_qty_after' => (int) (($field === 'eta_qty' ? $normalizedValue : $inventory->eta_qty) ?? 0),
                'reference_type' => 'tyre_offer',
                'reference_id' => $offer->id,
                'notes' => 'Updated via Tyre Inventory Grid',
                'user_id' => auth()->id(),
            ]);

            $this->statusSync->sync($offer);
        }
    }

    protected function normalizeInventoryValue(string $field, $value)
    {
        if ($field === 'eta') {
            $value = trim((string) ($value ?? ''));

            return $value === '' ? null : $value;
        }

        if ($value === null || $value === '') {
            return 0;
        }

        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return max(0, (int) $value);
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'importFile' => 'required|mimes:csv,xlsx,txt',
        ]);

        if ($validator->fails()) {
            return back()->with([
                'error' => 'Invalid file. Please upload CSV or Excel file.',
            ]);
        }

        try {
            ini_set('memory_limit', '2G');
            ini_set('max_execution_time', 600);

            $this->ensureInventoryEntitlement($request);

            DB::beginTransaction();

            $currentAccountId = $this->currentAccountId($request);
            $file = $request->file('importFile');
            $data = Excel::toArray([], $file)[0];

            $headers = array_shift($data);

            $warehouseColumns = [];
            foreach ($headers as $index => $header) {
                if ($index === 0) {
                    continue;
                }

                if (preg_match('/^(.+?)(?:_eta|_incoming|_quantity_inbound)?$/', $header, $matches)) {
                    $code = $matches[1];
                    if (! isset($warehouseColumns[$code])) {
                        $warehouse = Warehouse::query()
                            ->where('account_id', $currentAccountId)
                            ->where('code', $code)
                            ->first();

                        if ($warehouse) {
                            $warehouseColumns[$code] = [
                                'warehouse' => $warehouse,
                                'qty_col' => $index,
                                'eta_col' => null,
                                'eta_qty_col' => null,
                            ];
                        }
                    }

                    if (isset($warehouseColumns[$code])) {
                        if (str_ends_with($header, '_eta')) {
                            $warehouseColumns[$code]['eta_col'] = $index;
                        } elseif (str_ends_with($header, '_incoming') || str_ends_with($header, '_quantity_inbound')) {
                            $warehouseColumns[$code]['eta_qty_col'] = $index;
                        } else {
                            $warehouseColumns[$code]['qty_col'] = $index;
                        }
                    }
                }
            }

            $importedCount = 0;

            foreach ($data as $row) {
                $sku = trim((string) ($row[0] ?? ''));

                if ($sku === '') {
                    continue;
                }

                $offer = TyreAccountOffer::query()
                    ->where('account_id', $currentAccountId)
                    ->where('source_sku', $sku)
                    ->first();

                if (! $offer) {
                    Log::warning("Tyre SKU not found for import: {$sku}");
                    continue;
                }

                foreach ($warehouseColumns as $columns) {
                    $warehouse = $columns['warehouse'];
                    $qty = $row[$columns['qty_col']] ?? 0;
                    $eta = $columns['eta_col'] !== null ? ($row[$columns['eta_col']] ?? '') : '';
                    $etaQty = $columns['eta_qty_col'] !== null ? ($row[$columns['eta_qty_col']] ?? 0) : 0;

                    $this->upsertInventory->execute(
                        offer: $offer,
                        warehouse: $warehouse,
                        quantity: (int) $qty,
                        eta: filled($eta) ? (string) $eta : null,
                        etaQty: (int) $etaQty,
                        actor: auth()->user(),
                        referenceType: 'tyre_inventory_import',
                        referenceId: $offer->id,
                        notes: 'Imported from Excel',
                        action: InventoryLog::ACTION_IMPORT,
                    );
                }

                $importedCount++;
            }

            DB::commit();

            return back()->with([
                'success' => "Successfully imported tyre inventory for {$importedCount} offers.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tyre inventory import error: ' . $e->getMessage());

            return back()->with([
                'error' => 'Import failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function downloadTemplate(Request $request)
    {
        $this->ensureInventoryEntitlement($request);

        $currentAccountId = $this->currentAccountId($request);

        $warehouses = Warehouse::query()
            ->orderBy('warehouse_name')
            ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
            ->where('code', '!=', 'NON-STOCK')
            ->get(['id', 'warehouse_name', 'code']);

        $headers = ['SKU'];
        foreach ($warehouses as $warehouse) {
            $headers[] = $warehouse->code;
            $headers[] = $warehouse->code . '_eta';
            $headers[] = $warehouse->code . '_incoming';
        }

        $example1 = ['TYRE-DEMO-001'];
        $example2 = ['TYRE-DEMO-002'];
        foreach ($warehouses as $warehouse) {
            $example1[] = '12';
            $example1[] = '';
            $example1[] = '0';
            $example2[] = '0';
            $example2[] = '2026-04-30';
            $example2[] = '8';
        }

        $filename = 'tyre-inventory-template-' . now()->format('Ymd') . '.csv';

        $callback = function () use ($headers, $example1, $example2) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $example1);
            fputcsv($out, $example2);
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function exportCsv(Request $request)
    {
        $this->ensureInventoryEntitlement($request);

        $currentAccountId = $this->currentAccountId($request);

        $warehouses = Warehouse::query()
            ->orderBy('warehouse_name')
            ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
            ->where('code', '!=', 'NON-STOCK')
            ->get(['id', 'warehouse_name', 'code']);

        $headers = ['SKU'];
        foreach ($warehouses as $warehouse) {
            $headers[] = $warehouse->code;
            $headers[] = $warehouse->code . '_eta';
            $headers[] = $warehouse->code . '_incoming';
        }

        $offers = TyreAccountOffer::query()
            ->where('account_id', $currentAccountId)
            ->select('id', 'source_sku')
            ->orderBy('source_sku')
            ->get();

        $inventoryMap = TyreOfferInventory::query()
            ->whereIn('tyre_account_offer_id', $offers->pluck('id')->all())
            ->when(
                $warehouses->isNotEmpty(),
                fn ($query) => $query->whereIn('warehouse_id', $warehouses->pluck('id')->all())
            )
            ->get()
            ->groupBy('tyre_account_offer_id');

        $filename = 'tyre-inventory-import-ready-' . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($headers, $warehouses, $offers, $inventoryMap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($offers as $offer) {
                if (blank($offer->source_sku)) {
                    continue;
                }

                $row = [$offer->source_sku];
                $offerInventory = $inventoryMap->get($offer->id, collect())->keyBy('warehouse_id');

                foreach ($warehouses as $warehouse) {
                    $inventory = $offerInventory->get($warehouse->id);
                    $row[] = $inventory ? ($inventory->quantity ?? 0) : 0;
                    $row[] = $inventory ? ($inventory->eta ?? '') : '';
                    $row[] = $inventory ? ($inventory->eta_qty ?? 0) : 0;
                }

                fputcsv($out, $row);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function bulkTransfer(Request $request)
    {
        $this->ensureInventoryEntitlement($request);

        $lines = $request->input('lines', []);
        $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);
        $currentAccountId = $this->currentAccountId($request);

        if (empty($lines)) {
            return response()->json(['success' => false, 'message' => 'No transfer lines provided.'], 422);
        }

        try {
            DB::beginTransaction();
            $processedCount = 0;

            foreach ($lines as $line) {
                $offerId = $line['offer_id'] ?? null;
                $from = $line['from'] ?? null;
                $to = $line['to'] ?? null;
                $qty = (int) ($line['quantity'] ?? 0);

                $offer = TyreAccountOffer::query()
                    ->where('account_id', $currentAccountId)
                    ->find($offerId);

                if (! $offer) {
                    throw new \Exception("Tyre offer #{$offerId} not found.");
                }

                if ($qty <= 0) {
                    throw new \Exception('Transfer quantity must be greater than zero.');
                }

                if (str_starts_with((string) $from, 'incoming_')) {
                    $fromWhId = (int) str_replace('incoming_', '', (string) $from);
                    if (! in_array($fromWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected source warehouse is not available to the current business account.');
                    }

                    $inventory = TyreOfferInventory::query()->firstOrNew([
                        'tyre_account_offer_id' => $offer->id,
                        'warehouse_id' => $fromWhId,
                    ]);
                    $inventory->account_id = $offer->account_id;
                    $available = (int) ($inventory->eta_qty ?? 0);

                    if ($available < $qty) {
                        throw new \Exception("Insufficient incoming stock for offer #{$offerId}. Has: {$available}, needs: {$qty}.");
                    }

                    $oldEtaQty = $available;
                    $inventory->eta_qty = $oldEtaQty - $qty;
                    $inventory->save();

                    InventoryLog::create([
                        'warehouse_id' => $fromWhId,
                        'tyre_account_offer_id' => $offer->id,
                        'action' => InventoryLog::ACTION_TRANSFER_OUT,
                        'quantity_before' => $oldEtaQty,
                        'quantity_after' => $inventory->eta_qty,
                        'quantity_change' => -$qty,
                        'eta_qty_before' => $oldEtaQty,
                        'eta_qty_after' => $inventory->eta_qty,
                        'reference_type' => 'tyre_offer',
                        'reference_id' => $offer->id,
                        'notes' => "Bulk transfer: incoming WH #{$fromWhId} to WH #{$to}",
                        'user_id' => auth()->id(),
                    ]);
                } else {
                    $fromWhId = (int) $from;
                    if (! in_array($fromWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected source warehouse is not available to the current business account.');
                    }

                    $inventory = TyreOfferInventory::query()->firstOrNew([
                        'tyre_account_offer_id' => $offer->id,
                        'warehouse_id' => $fromWhId,
                    ]);
                    $inventory->account_id = $offer->account_id;
                    $available = (int) ($inventory->quantity ?? 0);

                    if ($available < $qty) {
                        throw new \Exception("Insufficient stock in warehouse #{$fromWhId} for offer #{$offerId}. Has: {$available}, needs: {$qty}.");
                    }

                    $oldQty = $available;
                    $inventory->quantity = $oldQty - $qty;
                    $inventory->save();

                    InventoryLog::create([
                        'warehouse_id' => $fromWhId,
                        'tyre_account_offer_id' => $offer->id,
                        'action' => InventoryLog::ACTION_TRANSFER_OUT,
                        'quantity_before' => $oldQty,
                        'quantity_after' => $inventory->quantity,
                        'quantity_change' => -$qty,
                        'reference_type' => 'tyre_offer',
                        'reference_id' => $offer->id,
                        'notes' => "Bulk transfer out to WH #{$to}",
                        'user_id' => auth()->id(),
                    ]);
                }

                $toWhId = (int) $to;
                if (! in_array($toWhId, $allowedWarehouseIds, true)) {
                    throw new \RuntimeException('The selected destination warehouse is not available to the current business account.');
                }

                $destination = TyreOfferInventory::query()->firstOrNew([
                    'tyre_account_offer_id' => $offer->id,
                    'warehouse_id' => $toWhId,
                ]);
                $destination->account_id = $offer->account_id;
                $oldDestinationQty = (int) ($destination->quantity ?? 0);
                $destination->quantity = $oldDestinationQty + $qty;
                $destination->save();

                InventoryLog::create([
                    'warehouse_id' => $toWhId,
                    'tyre_account_offer_id' => $offer->id,
                    'action' => InventoryLog::ACTION_TRANSFER_IN,
                    'quantity_before' => $oldDestinationQty,
                    'quantity_after' => $destination->quantity,
                    'quantity_change' => $qty,
                    'reference_type' => 'tyre_offer',
                    'reference_id' => $offer->id,
                    'notes' => "Bulk transfer in from {$from}",
                    'user_id' => auth()->id(),
                ]);

                $this->statusSync->sync($offer);
                $processedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully transferred {$processedCount} line(s).",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tyre bulk transfer error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addInventory(Request $request)
    {
        $this->ensureInventoryEntitlement($request);

        $lines = $request->input('lines', []);
        $reference = $request->input('reference', '');
        $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);
        $currentAccountId = $this->currentAccountId($request);

        if (empty($lines)) {
            return response()->json(['success' => false, 'message' => 'No lines provided.'], 422);
        }

        try {
            DB::beginTransaction();
            $count = 0;

            foreach ($lines as $line) {
                $offerId = $line['offer_id'] ?? null;
                $to = $line['to'] ?? null;
                $qty = (int) ($line['quantity'] ?? 0);

                $offer = TyreAccountOffer::query()
                    ->where('account_id', $currentAccountId)
                    ->find($offerId);

                if (! $offer) {
                    throw new \Exception("Tyre offer #{$offerId} not found.");
                }

                if (str_starts_with((string) $to, 'incoming_')) {
                    $toWhId = (int) str_replace('incoming_', '', (string) $to);
                    if (! in_array($toWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected warehouse is not available to the current business account.');
                    }

                    $inventory = TyreOfferInventory::query()->firstOrNew([
                        'tyre_account_offer_id' => $offer->id,
                        'warehouse_id' => $toWhId,
                    ]);
                    $inventory->account_id = $offer->account_id;
                    $oldEtaQty = (int) ($inventory->eta_qty ?? 0);
                    $inventory->eta_qty = $oldEtaQty + $qty;
                    $inventory->save();

                    InventoryLog::create([
                        'warehouse_id' => $toWhId,
                        'tyre_account_offer_id' => $offer->id,
                        'action' => InventoryLog::ACTION_IMPORT,
                        'quantity_before' => $oldEtaQty,
                        'quantity_after' => $inventory->eta_qty,
                        'quantity_change' => $qty,
                        'eta_qty_before' => $oldEtaQty,
                        'eta_qty_after' => $inventory->eta_qty,
                        'reference_type' => 'tyre_offer',
                        'reference_id' => $offer->id,
                        'notes' => 'Add Tyre Inventory to incoming. Ref: ' . ($reference ?: 'N/A'),
                        'user_id' => auth()->id(),
                    ]);
                } else {
                    $toWhId = (int) $to;
                    if (! in_array($toWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected warehouse is not available to the current business account.');
                    }

                    $inventory = TyreOfferInventory::query()->firstOrNew([
                        'tyre_account_offer_id' => $offer->id,
                        'warehouse_id' => $toWhId,
                    ]);
                    $inventory->account_id = $offer->account_id;
                    $oldQty = (int) ($inventory->quantity ?? 0);
                    $inventory->quantity = $oldQty + $qty;
                    $inventory->save();

                    InventoryLog::create([
                        'warehouse_id' => $toWhId,
                        'tyre_account_offer_id' => $offer->id,
                        'action' => InventoryLog::ACTION_IMPORT,
                        'quantity_before' => $oldQty,
                        'quantity_after' => $inventory->quantity,
                        'quantity_change' => $qty,
                        'reference_type' => 'tyre_offer',
                        'reference_id' => $offer->id,
                        'notes' => 'Add Tyre Inventory. Ref: ' . ($reference ?: 'N/A'),
                        'user_id' => auth()->id(),
                    ]);
                }

                $this->statusSync->sync($offer);
                $count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully added inventory for {$count} line(s).",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tyre add inventory error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
