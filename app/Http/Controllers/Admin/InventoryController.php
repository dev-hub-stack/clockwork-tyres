<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\InventoryLog;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    private function currentAccountId(?Request $request = null): ?int
    {
        $request ??= request();

        if (! $request || ! auth()->check()) {
            return null;
        }

        return app(CurrentAccountResolver::class)
            ->resolve($request, auth()->user())
            ->currentAccount?->id;
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

    /**
     * Save batch inventory updates from pqGrid
     * Matching: C:\Users\Dell\Documents\Reporting\app\Http\Controllers\ProductInventoryController.php
     */
    public function saveBatch(Request $request)
    {
        // Start output buffering to catch any PHP warnings/notices
        ob_start();
        
        try {
            // Suppress any PHP warnings/notices that might break JSON response
            error_reporting(E_ERROR | E_PARSE);
            ini_set('display_errors', '0');
            
            // Increase limits for bulk operations
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', 300);
            ini_set('max_input_vars', '10000');
            
            // Clean output buffer
            ob_clean();
            
            $changes = $request->input('list', []);
            
            if (empty($changes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No changes to save',
                    'addList' => [],
                    'updateList' => [],
                    'deleteList' => []
                ]);
            }

            DB::beginTransaction();

            $updateList = $changes['updateList'] ?? [];
            $addList = $changes['addList'] ?? [];
            $deleteList = $changes['deleteList'] ?? [];

            $updatedRows = [];
            $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);

            // Process updates
            foreach ($updateList as $row) {
                if (isset($row['id'])) {
                    $variant = ProductVariant::find($row['id']);
                    
                    if ($variant) {
                        // Process warehouse columns (qty{id}, eta{id}, e_ta_q_ty{id})
                        foreach ($row as $key => $value) {
                            // Quantity columns: qty{warehouse_id}
                            if (preg_match('/^qty(\d+)$/', $key, $matches)) {
                                $warehouseId = $matches[1];
                                $this->updateInventory($variant, $warehouseId, 'quantity', $value, $allowedWarehouseIds);
                            }
                            // ETA columns: eta{warehouse_id}
                            elseif (preg_match('/^eta(\d+)$/', $key, $matches)) {
                                $warehouseId = $matches[1];
                                $this->updateInventory($variant, $warehouseId, 'eta', $value, $allowedWarehouseIds);
                            }
                            // ETA Qty columns: e_ta_q_ty{warehouse_id}
                            elseif (preg_match('/^e_ta_q_ty(\d+)$/', $key, $matches)) {
                                $warehouseId = $matches[1];
                                $this->updateInventory($variant, $warehouseId, 'eta_qty', $value, $allowedWarehouseIds);
                            }
                        }
                    }
                }
                
                $updatedRows[] = $row;
            }

            DB::commit();

            // Clean any warnings from output buffer
            ob_clean();
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory updated successfully',
                'addList' => $addList,
                'updateList' => $updatedRows,
                'deleteList' => $deleteList
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean output buffer on error too
            ob_clean();
            
            Log::error('Inventory save batch error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'errors' => [$e->getMessage()],
                'addList' => [],
                'updateList' => [],
                'deleteList' => []
            ], 500);
        } finally {
            // End output buffering
            if (ob_get_length()) ob_end_clean();
        }
    }

    /**
     * Update inventory for a specific warehouse
     */
    protected function updateInventory($variant, $warehouseId, $field, $value, array $allowedWarehouseIds = [])
    {
        if (! empty($allowedWarehouseIds) && ! in_array((int) $warehouseId, $allowedWarehouseIds, true)) {
            throw new \RuntimeException('The selected warehouse is not available to the current business account.');
        }

        $inventory = ProductInventory::firstOrNew([
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'warehouse_id' => $warehouseId
        ]);

        $inventory->quantity = $inventory->quantity ?? 0;
        $inventory->eta_qty = $inventory->eta_qty ?? 0;

        $normalizedValue = $this->normalizeInventoryValue($field, $value);
        $oldValue = $inventory->{$field} ?? null;
        
        // Only update if value changed
        if ($oldValue != $normalizedValue) {
            $inventory->{$field} = $normalizedValue;
            $inventory->save();

            $quantityBefore = (int) (($field === 'quantity' ? $oldValue : $inventory->quantity) ?? 0);
            $quantityAfter = (int) (($field === 'quantity' ? $normalizedValue : $inventory->quantity) ?? 0);

            // Log the change
            InventoryLog::create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $inventory->product_variant_id,
                'action' => InventoryLog::ACTION_ADJUSTMENT,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'quantity_change' => $field === 'quantity' ? ($quantityAfter - $quantityBefore) : 0,
                'eta_before' => $field === 'eta' ? $oldValue : $inventory->eta,
                'eta_after' => $field === 'eta' ? $normalizedValue : $inventory->eta,
                'eta_qty_before' => (int) (($field === 'eta_qty' ? $oldValue : $inventory->eta_qty) ?? 0),
                'eta_qty_after' => (int) (($field === 'eta_qty' ? $normalizedValue : $inventory->eta_qty) ?? 0),
                'notes' => 'Updated via Inventory Grid',
                'user_id' => auth()->id()
            ]);

            // Update product total_quantity
            if ($field === 'quantity' || $field === 'eta_qty') {
                $this->updateProductTotals($variant->product_id);
            }
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

    /**
     * Update product total quantity across all warehouses
     */
    protected function updateProductTotals($productId)
    {
        $total = ProductInventory::where('product_id', $productId)
            ->sum(DB::raw('quantity + eta_qty'));
        
        DB::table('products')
            ->where('id', $productId)
            ->update(['total_quantity' => $total]);
    }

    /**
     * Import inventory from Excel/CSV
     * Matching: C:\Users\Dell\Documents\Reporting\app\Http\Controllers\ProductInventoryController.php
     */
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

            DB::beginTransaction();
            $currentAccountId = $this->currentAccountId($request);

            if (! $currentAccountId) {
                throw new \RuntimeException('No active business account is selected for inventory import.');
            }

            $file = $request->file('importFile');
            $data = Excel::toArray([], $file)[0];
            
            // Remove header row
            $headers = array_shift($data);
            
            // Get warehouse codes from headers (columns after SKU)
            $warehouseColumns = [];
            foreach ($headers as $index => $header) {
                if ($index > 0) { // Skip SKU column
                    // Match pattern: WH-CODE, WH-CODE_eta, WH-CODE_incoming (or legacy _quantity_inbound)
                    if (preg_match('/^(.+?)(?:_eta|_incoming|_quantity_inbound)?$/', $header, $matches)) {
                        $code = $matches[1];
                        if (!isset($warehouseColumns[$code])) {
                            $warehouse = Warehouse::query()
                                ->where('account_id', $currentAccountId)
                                ->where('code', $code)
                                ->first();
                            if ($warehouse) {
                                $warehouseColumns[$code] = [
                                    'id' => $warehouse->id,
                                    'qty_col' => $index,
                                    'eta_col' => null,
                                    'eta_qty_col' => null
                                ];
                            }
                        }
                        
                        // Only update columns if this warehouse was found in DB
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
            }

            $importedCount = 0;
            
            foreach ($data as $row) {
                $sku = $row[0] ?? null;
                
                if (empty($sku)) continue;

                $variant = ProductVariant::where('sku', trim($sku))->first();
                
                if (!$variant) {
                    Log::warning("SKU not found: {$sku}");
                    continue;
                }

                // Process each warehouse
                foreach ($warehouseColumns as $code => $cols) {
                    $qty = $row[$cols['qty_col']] ?? 0;
                    $eta = $cols['eta_col'] ? ($row[$cols['eta_col']] ?? '') : '';
                    $etaQty = $cols['eta_qty_col'] ? ($row[$cols['eta_qty_col']] ?? 0) : 0;

                    $inventory = ProductInventory::updateOrCreate(
                        [
                            'product_variant_id' => $variant->id,
                            'product_id' => $variant->product_id,
                            'warehouse_id' => $cols['id']
                        ],
                        [
                            'quantity' => $qty,
                            'eta' => $eta,
                            'eta_qty' => $etaQty
                        ]
                    );

                    // Log import
                    InventoryLog::create([
                        'warehouse_id' => $cols['id'],
                        'product_variant_id' => $inventory->product_variant_id,
                        'action' => InventoryLog::ACTION_IMPORT,
                        'quantity_before' => 0,
                        'quantity_after' => $qty,
                        'quantity_change' => $qty,
                        'eta_after' => $eta,
                        'eta_qty_after' => $etaQty,
                        'notes' => 'Imported from Excel',
                        'user_id' => auth()->id()
                    ]);
                }

                $importedCount++;
            }

            DB::commit();

            return back()->with([
                'success' => "Successfully imported inventory for {$importedCount} products.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inventory import error: ' . $e->getMessage());
            
            return back()->with([
                'error' => 'Import failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Download a dynamically-generated CSV template based on current warehouses.
     * Columns: SKU, WH-CODE, WH-CODE_eta, WH-CODE_quantity_inbound (repeated per warehouse)
     */
    public function downloadTemplate()
    {
        $currentAccountId = $this->currentAccountId();

        // Exclude non-physical warehouses (NON-STOCK) from the template
        $warehouses = Warehouse::orderBy('warehouse_name')
            ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
            ->where('code', '!=', 'NON-STOCK')
            ->get(['id', 'warehouse_name', 'code']);

        // Build header row
        $headers = ['SKU'];
        foreach ($warehouses as $wh) {
            $headers[] = $wh->code;               // stock qty
            $headers[] = $wh->code . '_eta';       // ETA date for incoming stock
            $headers[] = $wh->code . '_incoming';  // incoming/inbound qty
        }

        // Build two example rows
        $example1 = ['1785ABR-86140G12'];
        $example2 = ['4P06-20100-5D55-1806'];
        foreach ($warehouses as $wh) {
            $example1[] = '50';  // qty
            $example1[] = '';    // eta
            $example1[] = '0';   // eta_qty
            $example2[] = '0';
            $example2[] = '2026-04-01';
            $example2[] = '20';
        }

        $filename = 'inventory-template-' . now()->format('Ymd') . '.csv';

        $callback = function () use ($headers, $example1, $example2) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $example1);
            fputcsv($out, $example2);
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export all inventory as import-ready CSV.
     * Same column format as downloadTemplate() so admins can edit & re-import.
     */
    public function exportCsv()
    {
        $currentAccountId = $this->currentAccountId();

        $warehouses = Warehouse::orderBy('warehouse_name')
            ->when($currentAccountId, fn ($query) => $query->where('account_id', $currentAccountId))
            ->where('code', '!=', 'NON-STOCK')
            ->get(['id', 'warehouse_name', 'code']);

        // Build header: SKU, WH-CODE, WH-CODE_eta, WH-CODE_incoming, ...
        // No extra columns — format is identical to the import template
        $headers = ['SKU'];
        foreach ($warehouses as $wh) {
            $headers[] = $wh->code;
            $headers[] = $wh->code . '_eta';
            $headers[] = $wh->code . '_incoming';
        }

        // Fetch only variants whose product has track_inventory enabled
        $variants = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.track_inventory', true)
            ->whereNotNull('pv.sku')
            ->select('pv.id', 'pv.sku')
            ->orderBy('pv.sku')
            ->get();

        $variantIds = $variants->pluck('id')->all();
        $inventoryMap = ProductInventory::whereIn('product_variant_id', $variantIds)
            ->when(
                $warehouses->isNotEmpty(),
                fn ($query) => $query->whereIn('warehouse_id', $warehouses->pluck('id')->all())
            )
            ->get()->groupBy('product_variant_id');

        $filename = 'inventory-import-ready-' . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($headers, $warehouses, $variants, $inventoryMap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($variants as $variant) {
                if (empty($variant->sku)) continue;

                $row = [$variant->sku];
                $variantInventory = $inventoryMap->get($variant->id, collect())
                    ->keyBy('warehouse_id');

                foreach ($warehouses as $wh) {
                    $inv = $variantInventory->get($wh->id);
                    $row[] = $inv ? ($inv->quantity ?? 0) : 0;
                    $row[] = $inv ? ($inv->eta ?? '') : '';
                    $row[] = $inv ? ($inv->eta_qty ?? 0) : 0;
                }

                fputcsv($out, $row);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Bulk transfer inventory between warehouses (AJAX JSON format)
     * Accepts: { lines: [{ variant_id, from, to, quantity }] }
     * from/to = warehouse_id (int) or 'incoming'
     */
    public function bulkTransfer(Request $request)
    {
        $lines = $request->input('lines', []);
        $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);

        if (empty($lines)) {
            return response()->json(['success' => false, 'message' => 'No transfer lines provided.'], 422);
        }

        try {
            DB::beginTransaction();
            $processedCount = 0;

            foreach ($lines as $line) {
                $variantId = $line['variant_id'];
                $from      = $line['from'];  // int warehouse_id or 'incoming'
                $to        = $line['to'];    // int warehouse_id (never incoming for destination here)
                $qty       = (int) $line['quantity'];

                $variant = ProductVariant::find($variantId);
                if (!$variant) throw new \Exception("Variant #{$variantId} not found.");

                // ── DEDUCT FROM SOURCE ─────────────────────────────────────
                if (str_starts_with((string)$from, 'incoming_')) {
                    // Deduct from a specific warehouse's eta_qty
                    $fromWhId = (int) str_replace('incoming_', '', $from);
                    if (! in_array($fromWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected source warehouse is not available to the current business account.');
                    }
                    $inv = ProductInventory::firstOrNew([
                        'product_variant_id' => $variantId,
                        'warehouse_id'        => $fromWhId,
                    ]);
                    if (!$inv->exists) $inv->product_id = $variant->product_id;
                    $available = (int)($inv->eta_qty ?? 0);
                    if ($available < $qty) {
                        $wh = Warehouse::find($fromWhId);
                        $whCode = $wh ? $wh->code : "#{$fromWhId}";
                        throw new \Exception("Insufficient incoming stock in {$whCode} for variant #{$variantId}. Has: {$available}, needs: {$qty}.");
                    }
                    $oldEta = $inv->eta_qty;
                    $inv->eta_qty = $available - $qty;
                    $inv->save();
                    InventoryLog::create([
                        'warehouse_id'      => $fromWhId,
                        'product_variant_id' => $variantId,
                        'action'            => InventoryLog::ACTION_TRANSFER_OUT,
                        'quantity_before'   => $oldEta,
                        'quantity_after'    => $inv->eta_qty,
                        'quantity_change'   => -$qty,
                        'notes'             => "Bulk transfer: incoming WH #{$fromWhId} → WH #{$to}",
                        'user_id'           => auth()->id(),
                    ]);
                } else {
                    if (! in_array((int) $from, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected source warehouse is not available to the current business account.');
                    }
                    $srcInv = ProductInventory::firstOrNew([
                        'product_variant_id' => $variantId,
                        'warehouse_id'        => $from,
                    ]);
                    if (!$srcInv->exists) $srcInv->product_id = $variant->product_id;
                    if (($srcInv->quantity ?? 0) < $qty) {
                        throw new \Exception("Insufficient stock in warehouse #{$from} for variant #{$variantId}. Has: {$srcInv->quantity}, needs: {$qty}.");
                    }
                    $oldQty = $srcInv->quantity;
                    $srcInv->quantity = $oldQty - $qty;
                    $srcInv->save();
                    InventoryLog::create([
                        'warehouse_id'      => $from,
                        'product_variant_id' => $variantId,
                        'action'            => InventoryLog::ACTION_TRANSFER_OUT,
                        'quantity_before'   => $oldQty,
                        'quantity_after'    => $srcInv->quantity,
                        'quantity_change'   => -$qty,
                        'notes'             => "Bulk transfer out → WH #{$to}",
                        'user_id'           => auth()->id(),
                    ]);
                }

                // ── ADD TO DESTINATION ─────────────────────────────────────
                if (! in_array((int) $to, $allowedWarehouseIds, true)) {
                    throw new \RuntimeException('The selected destination warehouse is not available to the current business account.');
                }

                $dstInv = ProductInventory::firstOrNew([
                    'product_variant_id' => $variantId,
                    'warehouse_id'        => $to,
                ]);
                if (!$dstInv->exists) $dstInv->product_id = $variant->product_id;
                $oldDst = $dstInv->quantity ?? 0;
                $dstInv->quantity = $oldDst + $qty;
                $dstInv->save();
                InventoryLog::create([
                    'warehouse_id'      => $to,
                    'product_variant_id' => $variantId,
                    'action'            => InventoryLog::ACTION_TRANSFER_IN,
                    'quantity_before'   => $oldDst,
                    'quantity_after'    => $dstInv->quantity,
                    'quantity_change'   => $qty,
                    'notes'             => "Bulk transfer in from " . (str_starts_with((string)$from, 'incoming_') ? 'Incoming WH #'.str_replace('incoming_','',$from) : "WH #{$from}"),
                    'user_id'           => auth()->id(),
                ]);

                $this->updateProductTotals($variant->product_id);
                $processedCount++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully transferred {$processedCount} line(s).",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk transfer error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Add inventory via the Add Inventory modal (AJAX JSON)
     * Accepts: { lines: [{ variant_id, to, quantity }], reference }
     * to = warehouse_id (int) or 'incoming'
     */
    public function addInventory(Request $request)
    {
        $lines     = $request->input('lines', []);
        $reference = $request->input('reference', '');
        $allowedWarehouseIds = $this->warehouseIdsForCurrentAccount($request);

        if (empty($lines)) {
            return response()->json(['success' => false, 'message' => 'No lines provided.'], 422);
        }

        try {
            DB::beginTransaction();
            $count = 0;

            foreach ($lines as $line) {
                $variantId = $line['variant_id'];
                $to        = $line['to'];
                $qty       = (int) $line['quantity'];

                $variant = ProductVariant::find($variantId);
                if (!$variant) throw new \Exception("Variant #{$variantId} not found.");

                if (str_starts_with((string)$to, 'incoming_')) {
                    // Add to a specific warehouse's eta_qty (incoming stock)
                    $toWhId = (int) str_replace('incoming_', '', $to);
                    if (! in_array($toWhId, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected warehouse is not available to the current business account.');
                    }
                    $inv = ProductInventory::firstOrNew([
                        'product_variant_id' => $variantId,
                        'warehouse_id'        => $toWhId,
                    ]);
                    if (!$inv->exists) $inv->product_id = $variant->product_id;
                    $old = $inv->eta_qty ?? 0;
                    $inv->eta_qty = $old + $qty;
                    $inv->save();
                    InventoryLog::create([
                        'warehouse_id'      => $toWhId,
                        'product_variant_id' => $variantId,
                        'action'            => InventoryLog::ACTION_IMPORT,
                        'quantity_before'   => $old,
                        'quantity_after'    => $inv->eta_qty,
                        'quantity_change'   => $qty,
                        'notes'             => 'Add Inventory → Incoming WH #'.$toWhId.'. Ref: ' . ($reference ?: 'N/A'),
                        'user_id'           => auth()->id(),
                    ]);
                } else {
                    if (! in_array((int) $to, $allowedWarehouseIds, true)) {
                        throw new \RuntimeException('The selected warehouse is not available to the current business account.');
                    }
                    $inv = ProductInventory::firstOrNew([
                        'product_variant_id' => $variantId,
                        'warehouse_id'        => $to,
                    ]);
                    if (!$inv->exists) $inv->product_id = $variant->product_id;
                    $old = $inv->quantity ?? 0;
                    $inv->quantity = $old + $qty;
                    $inv->save();
                    InventoryLog::create([
                        'warehouse_id'      => $to,
                        'product_variant_id' => $variantId,
                        'action'            => InventoryLog::ACTION_IMPORT,
                        'quantity_before'   => $old,
                        'quantity_after'    => $inv->quantity,
                        'quantity_change'   => $qty,
                        'notes'             => 'Add Inventory. Ref: ' . ($reference ?: 'N/A'),
                        'user_id'           => auth()->id(),
                    ]);
                    $this->updateProductTotals($variant->product_id);
                }
                $count++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully added inventory for {$count} line(s).",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add inventory error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Return inventory movement log as JSON for the log page.
     * GET /admin/inventory/log-data
     */
    public function logData(Request $request)
    {
        $currentAccountId = $this->currentAccountId($request);

        $query = DB::table('inventory_logs as il')
            ->leftJoin('warehouses as w', 'w.id', '=', 'il.warehouse_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'il.product_variant_id')
            ->leftJoin('addons as a', 'a.id', '=', 'il.add_on_id')
            ->leftJoin('users as u', 'u.id', '=', 'il.user_id')
            ->select(
                'il.id',
                'il.action',
                'il.quantity_before',
                'il.quantity_after',
                'il.quantity_change',
                'il.eta_qty_before',
                'il.eta_qty_after',
                'il.notes',
                'il.created_at',
                'w.code as warehouse_code',
                'w.warehouse_name',
                DB::raw('COALESCE(pv.sku, a.part_number) as sku'),
                'u.name as user_name'
            )
            ->when($currentAccountId, fn ($query) => $query->where('w.account_id', $currentAccountId))
            ->orderByDesc('il.id');

        if ($request->filled('sku'))          $query->where(function($q) use ($request) {
            $q->where('pv.sku', 'like', '%' . $request->sku . '%')
              ->orWhere('a.part_number', 'like', '%' . $request->sku . '%');
        });
        if ($request->filled('action'))       $query->where('il.action', $request->action);
        if ($request->filled('warehouse_id')) $query->where('il.warehouse_id', $request->warehouse_id);
        if ($request->filled('from_date'))    $query->whereDate('il.created_at', '>=', $request->from_date);
        if ($request->filled('to_date'))      $query->whereDate('il.created_at', '<=', $request->to_date);

        return response()->json($query->limit(500)->get());
    }
}
