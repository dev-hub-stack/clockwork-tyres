<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                                $this->updateInventory($variant, $warehouseId, 'quantity', $value);
                            }
                            // ETA columns: eta{warehouse_id}
                            elseif (preg_match('/^eta(\d+)$/', $key, $matches)) {
                                $warehouseId = $matches[1];
                                $this->updateInventory($variant, $warehouseId, 'eta', $value);
                            }
                            // ETA Qty columns: e_ta_q_ty{warehouse_id}
                            elseif (preg_match('/^e_ta_q_ty(\d+)$/', $key, $matches)) {
                                $warehouseId = $matches[1];
                                $this->updateInventory($variant, $warehouseId, 'eta_qty', $value);
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
    protected function updateInventory($variant, $warehouseId, $field, $value)
    {
        $inventory = ProductInventory::firstOrNew([
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'warehouse_id' => $warehouseId
        ]);

        $oldValue = $inventory->{$field} ?? null;
        
        // Only update if value changed
        if ($oldValue != $value) {
            $inventory->{$field} = $value;
            $inventory->save();

            // Log the change
            InventoryLog::create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $inventory->product_variant_id,
                'action' => InventoryLog::ACTION_ADJUSTMENT,
                'quantity_before' => $field === 'quantity' ? $oldValue : $inventory->quantity,
                'quantity_after' => $field === 'quantity' ? $value : $inventory->quantity,
                'quantity_change' => $field === 'quantity' ? ($value - ($oldValue ?? 0)) : 0,
                'eta_before' => $field === 'eta' ? $oldValue : $inventory->eta,
                'eta_after' => $field === 'eta' ? $value : $inventory->eta,
                'eta_qty_before' => $field === 'eta_qty' ? $oldValue : $inventory->eta_qty,
                'eta_qty_after' => $field === 'eta_qty' ? $value : $inventory->eta_qty,
                'notes' => 'Updated via Inventory Grid',
                'user_id' => auth()->id()
            ]);

            // Update product total_quantity
            if ($field === 'quantity' || $field === 'eta_qty') {
                $this->updateProductTotals($variant->product_id);
            }
        }
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

            $file = $request->file('importFile');
            $data = Excel::toArray([], $file)[0];
            
            // Remove header row
            $headers = array_shift($data);
            
            // Get warehouse codes from headers (columns after SKU)
            $warehouseColumns = [];
            foreach ($headers as $index => $header) {
                if ($index > 0) { // Skip SKU column
                    // Match pattern: WH-CODE, WH-CODE_eta, WH-CODE_quantity_inbound
                    if (preg_match('/^(.+?)(?:_eta|_quantity_inbound)?$/', $header, $matches)) {
                        $code = $matches[1];
                        if (!isset($warehouseColumns[$code])) {
                            $warehouse = Warehouse::where('code', $code)->first();
                            if ($warehouse) {
                                $warehouseColumns[$code] = [
                                    'id' => $warehouse->id,
                                    'qty_col' => $index,
                                    'eta_col' => null,
                                    'eta_qty_col' => null
                                ];
                            }
                        }
                        
                        // Determine column type
                        if (str_ends_with($header, '_eta')) {
                            $warehouseColumns[$code]['eta_col'] = $index;
                        } elseif (str_ends_with($header, '_quantity_inbound')) {
                            $warehouseColumns[$code]['eta_qty_col'] = $index;
                        } else {
                            $warehouseColumns[$code]['qty_col'] = $index;
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
}
