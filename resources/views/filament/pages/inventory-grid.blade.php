<x-filament-panels::page>
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- jQuery (required by pqGrid) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- jQuery UI CSS & JS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- pqGrid PRO CSS - LOCAL (Required for filter headers!) -->
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    
    <!-- Bootstrap 5 for styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 for Premium Toasts & Dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ── pqGrid Pager ─────────────────────────────────── */
        .pq-pager {
            display: flex !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 4px !important;
            padding: 4px 8px !important;
            min-height: 36px !important;
            background: #f3f4f6 !important;
            border-top: 1px solid #d1d5db !important;
            overflow: visible !important;
        }
        .pq-pager-input {
            width: 45px !important;
            border: 1px solid #9ca3af !important;
            padding: 2px 4px !important;
            border-radius: 3px !important;
            font-size: 13px !important;
            color: #111 !important;
            background: #fff !important;
            display: inline-block !important;
        }
        .pq-pager select {
            border: 1px solid #9ca3af !important;
            padding: 2px 4px !important;
            border-radius: 3px !important;
            font-size: 13px !important;
            color: #111 !important;
            background: #fff !important;
            display: inline-block !important;
        }
        .pq-pager-msg {
            font-size: 13px !important;
            color: #374151 !important;
            display: inline-block !important;
        }
        .pq-page-placeholder, .pq-pager span {
            font-size: 13px !important;
            color: #374151 !important;
        }
        .pq-separator { margin: 0 4px !important; }
        /* FULL WIDTH PAGE - Override Filament's max-width constraint */
        .fi-body {
            max-width: none !important;
            width: 100% !important;
        }
        
        .fi-main {
            max-width: none !important;
            width: 100% !important;
        }
        
        .fi-content {
            max-width: none !important;
            width: 100% !important;
        }
        
        .fi-section-content-ctn {
            max-width: none !important;
            width: 100% !important;
        }
        
        .page-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: none !important;
            width: 100% !important;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        #grid_json_inventory {
            width: 100% !important;
        }
        
        /* pqGrid container - ensure full width */
        .pq-grid {
            width: 100% !important;
        }
        
        .pq-grid-cont {
            width: 100% !important;
        }

        /* Warehouse column styling (matching old system) */
        .inventory-info-inner {
            background-color: #e8f5e9 !important;
            font-weight: 600;
        }

        .inventory-info-inner-eta {
            background-color: #fff3e0 !important;
        }

        .inventory-info-inner-eta_qty {
            background-color: #e3f2fd !important;
        }

        /* Filter header row - FORCE DISPLAY & STYLING */
        .pq-grid-header-search-row {
            display: table-row !important;
            visibility: visible !important;
            background-color: #f8f9fa !important;
        }

        .pq-grid-header-search-row .pq-grid-col {
            padding: 5px !important;
            background: #f8f9fa !important;
            border-color: #e5e7eb !important;
        }

        .pq-grid-hd-search-field {
            display: block !important;
            width: 100% !important;
            padding: 5px 8px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            background: #ffffff !important;
            color: #111827 !important;
        }

        .pq-grid-hd-search-field:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1) !important;
        }

        /* HIDE FILTER ICON/ARROW in column headers only - NOT in pager */
        .pq-grid-header-search-icon,
        .pq-grid-col .ui-icon-triangle-1-s,
        .pq-grid-col .ui-icon-carat-2-n-s,
        .pq-grid-title-row .ui-icon,
        .pq-grid-col .ui-icon,
        .pq-grid-title-row .pq-grid-col .ui-icon,
        .pq-grid-title-row .ui-state-default .ui-icon {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
        }
        
        /* Remove padding where icons were */
        .pq-grid-title-row .ui-state-default {
            padding-right: 10px !important;
        }

        /* Grid column headers */
        .pq-grid-col, .pq-grid-number-cell {
            border-color: #e5e7eb !important;
            background-color: #f3f4f6 !important;
        }

        .pq-grid-title-row .pq-grid-number-cell,
        .pq-grid-header-search-row .pq-grid-number-cell,
        .pq-grid-title-row .ui-state-default {
            background-color: #374151 !important;
            color: #fff !important;
        }

        /* Grid rows */
        .pq-grid-row.pq-striped {
            background: #f9fafb;
        }

        .pq-cont-inner > .pq-table > .pq-grid-row {
            border-bottom-color: #e5e7eb;
        }

        /* Toolbar styling */
        .pq-toolbar-export {
            background: #f8f9fa;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        /* Grid save button */
        .grid-save-btn {
            background: #0d6efd !important;
            color: white !important;
            padding: 6px 16px !important;
            border-radius: 4px !important;
        }

        .grid-save-btn:hover {
            background: #0b5ed7 !important;
        }
    </style>

    <div class="page-content">
        <!-- Action Buttons -->
        <div class="action-buttons mb-4">
            <button type="button" class="btn btn-primary" id="import-inv-btn">
                <i class="bi bi-cloud-download"></i> Import Inventory
            </button>
            <button type="button" class="btn btn-info" id="export-btn">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
            <button type="button" class="btn btn-warning" id="bulk-transfer-btn">
                <i class="bi bi-arrow-left-right"></i> Bulk Transfer
            </button>
            <button type="button" class="btn btn-success" id="save-changes-btn">
                <i class="bi bi-save"></i> Save Changes
            </button>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- pqGrid Container -->
        <div id="grid_json_inventory"></div>
    </div>

    <!-- Bulk Transfer Modal -->
    <div class="modal fade" id="bulk-transfer-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Bulk Transfer Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.inventory.bulk-transfer') }}" method="POST" id="bulkTransferForm">
                    @csrf
                    <input type="hidden" name="selected_ids" id="transfer_selected_ids" value="[]">
                    <div class="modal-body">
                        <div id="transfer_count_msg" class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> Loading selection...
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">From Warehouse</label>
                            <select name="source_warehouse_id" id="source_warehouse_id" class="form-select" required>
                                <option value="">-- Select source --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">To Warehouse</label>
                            <select name="destination_warehouse_id" id="destination_warehouse_id" class="form-select" required>
                                <option value="">-- Select destination --</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Quantity to Transfer (per item)</label>
                            <input type="number" name="quantity" id="transfer_quantity" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-warning" id="confirmBulkTransferBtn">
                            <i class="bi bi-arrow-left-right"></i> Transfer
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Modal (matching old system) -->
    <div class="modal" id="import-product-inventory" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.inventory.import') }}" method="POST" enctype="multipart/form-data" id="inventoryImportForm">
                    @csrf
                    <div class="modal-body">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="file" class="form-label">Drop File Here</label>
                                    <input type="file" name="importFile" class="form-control" 
                                           accept=".csv, .xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" 
                                           required/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sample File</label><br/>
                                    <a href="{{ asset('uploads/samplefiles/product-inventory.csv') }}" 
                                       download="product-inventory.csv" 
                                       class="btn btn-warning">
                                        <i class="bi bi-download"></i> Download Sample CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle"></i> CSV Format:</strong>
                            <ul class="mb-0">
                                <li>Required columns: <strong>SKU, Warehouse Code, Quantity</strong></li>
                                <li>Optional: ETA (YYYY-MM-DD), ETA Quantity</li>
                                <li>Warehouse Code must match existing warehouse codes</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" id="importInventoryBtn">
                            <i class="bi bi-cloud-upload"></i> Import
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Processing Loader Overlay (Like Tunerstop) -->
    <div id="processingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 40px 60px; border-radius: 10px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem; border-width: 0.3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="mt-3 mb-2" style="color: #333;">Processing Import...</h4>
            <p style="color: #666; margin: 0;">Please wait while we process your inventory data</p>
            <p style="color: #999; font-size: 14px; margin-top: 10px;">This may take a few moments for large files</p>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- pqGrid PRO JS - LOCAL (Required for filter headers!) -->
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
    
    <!-- FileSaver.js for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <!-- Embed data (EXACT structure from old Reporting system) -->
    <script>
        var api_data = @json($this->products_data);
        var allWarehouses = @json($this->warehouses);
        console.log('✅ Loaded ' + api_data.length + ' product variants');
        console.log('✅ Loaded ' + allWarehouses.length + ' warehouses');
    </script>

    <!-- Inventory Grid JavaScript (MATCHING old Reporting system) -->
    <script type="text/javascript">
        // Reusable SweetAlert2 Toast configuration
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        /**
         * Show a professional toast notification
         */
        function showToast(message, type = 'success') {
            Toast.fire({
                icon: type,
                title: message
            });
        }

        /**
         * Save changes to server (matching old system)
         */
        function saveChanges() {
            console.log('🔄 Save changes called');
            
            if (typeof grid === 'undefined' || !grid) {
                console.error('❌ Grid is not initialized!');
                alert('❌ Error: Grid is not ready. Please wait for the page to fully load.');
                return false;
            }
            
            console.log('✅ Grid object exists');
            
            if (grid.saveEditCell() === false) {
                console.log('❌ Failed to save edit cell');
                return false;
            }

            if (!$.active && grid.isDirty() && validationResult.valid) {
                var gridChanges = grid.getChanges({ format: 'byVal' });

                $.ajax({
                    url: "/admin/inventory/save-batch",
                    type: "POST",
                    contentType: "application/json",
                    data: JSON.stringify({ list: gridChanges }),
                    dataType: "json",
                    async: true,
                    beforeSend: function (jqXHR, settings) {
                        grid.option("strLoading", "Saving..");
                        grid.showLoading();
                    },
                    success: function (changes) {
                        grid.history({method: 'reset'});
                        grid.commit({ type: 'add', rows: changes.addList });
                        grid.commit({ type: 'update', rows: changes.updateList });
                        grid.commit({ type: 'delete', rows: changes.deleteList });
                        
                        showToast('✅ Inventory saved successfully!');
                    },
                    complete: function (resp) {
                        grid.hideLoading();
                        grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        showToast('❌ Failed to save inventory.', 'error');
                    }
                });
            } else {
                if ($.active) {
                    showToast('⏳ Save in progress...', 'info');
                } else if (!grid.isDirty()) {
                    showToast('ℹ️ No changes to save.', 'info');
                } else {
                    showToast('❌ Please fix validation errors.', 'error');
                }
            }
        }

        $(document).ready(function () {
            // Prepare data - EXACT structure from old Reporting system (lines 269-274)
            var data = [];
            api_data.forEach(function(element, index) {
                element.state = false; // Add state for checkbox selection
                element.inventory.forEach(function (el, ind){
                    element['qty'+el.warehouse_id] = el.quantity;
                    element['eta'+el.warehouse_id] = el.eta;
                    element['e_ta_q_ty'+el.warehouse_id] = el.eta_qty;
                });
                data[index] = element;
            });

            // Column definitions - base columns (matching old system lines 276-293)
            var colModel = [
                {
                    dataIndx: 'state',
                    title: '',
                    cb: { header: true, select: true, all: true },
                    type: 'checkbox',
                    cls: 'ui-state-default',
                    resizable: false,
                    width: 40,
                    minWidth: 40,
                    maxWidth: 40,
                    sortable: false,
                    filter: { crules: [] }
                },
                {
                    title: "SKU", 
                    width: 280, 
                    dataType: "string", 
                    align: "center",
                    dataIndx: "sku", 
                    validations: [{type: 'nonEmpty', msg: "SKU is required."}], 
                    editable: false, 
                    filter: { crules: [{ condition: 'begin' }] }  
                },
                { 
                    title: "Product Full Name", 
                    width: 450, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "product_full_name", 
                    validations: [{type: 'nonEmpty', msg: "full name is required."}], 
                    editable: false, 
                    filter: { crules: [{ condition: 'begin' }] }  
                },
                { 
                    title: "Size", 
                    width: 120, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "size", 
                    editable: false, 
                    filter: { crules: [{ condition: 'equal' }] }  
                },
                { 
                    title: "Bolt Pattern", 
                    width: 150, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "bolt_pattern",  
                    editable: false, 
                    filter: { crules: [{ condition: 'equal' }] }  
                },
                { 
                    title: "Offset", 
                    width: 120, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "offset", 
                    editable: false, 
                    filter: { crules: [{ condition: 'equal' }] }  
                }
            ];

            // DYNAMIC WAREHOUSE COLUMNS - EXACT pattern from old system (lines 295-304)
            var wj = 6; // Start after state, sku, name, size, bp, offset
            allWarehouses.forEach(function(warehouse, key) {
                let qtyWare = "qty"+warehouse.id;
                let etaWare = "eta"+warehouse.id;
                let etaWareQty = "e_ta_q_ty"+warehouse.id;
                
                // Quantity column
                let warehouseColumn = {
                    title: warehouse.code, 
                    dataIndx: qtyWare, 
                    width: 120, 
                    dataType: 'string', 
                    align: "center", 
                    cls: 'inventory-info-inner',
                    editable: true,
                    filter: { crules: [{ condition: 'equal' }] }
                };
                colModel[wj] = warehouseColumn;
                wj = wj+1;
                
                // ETA column
                let warehouseETAColumn = {
                    title: "ETA "+warehouse.code, 
                    dataIndx: etaWare, 
                    width: 180, 
                    dataType: 'string', 
                    align: "center", 
                    cls: 'inventory-info-inner-eta',
                    editable: true,
                    filter: { crules: [{ condition: 'begin' }] }
                };
                colModel[wj] = warehouseETAColumn;
                wj = wj+1;
                
                // Incoming Stock column (ETA Qty)
                let warehouseETAQtyColumn = {
                    title: "Incoming Stock "+warehouse.code, 
                    dataIndx: etaWareQty, 
                    width: 150, 
                    dataType: 'string', 
                    align: "center", 
                    cls: 'inventory-info-inner-eta_qty',
                    editable: true,
                    filter: { crules: [{ condition: 'equal' }] },
                    render: function(ui) {
                        let value = ui.cellData;
                        let sku = ui.rowData.sku;
                        if (value && parseInt(value) > 0) {
                            return '<a href="javascript:void(0);" class="incoming-link text-success fw-bold text-decoration-none" data-sku="' + sku + '" data-warehouse="' + warehouse.code + '" style="cursor: pointer;">' + value + '</a>';
                        }
                        return value || '';
                    }
                };
                colModel[wj] = warehouseETAQtyColumn;
                wj = wj+1;
            });

            // Consignment Stock column (total across all customers)
            let consignmentStockColumn = {
                title: "Consignment Stock",
                dataIndx: "consignment_stock",
                width: 150,
                dataType: "integer",
                align: "center",
                halign: "center",
                editable: false,
                filter: { crules: [{ condition: 'equal' }] },
                render: function(ui) {
                    let value = ui.cellData;
                    let sku = ui.rowData.sku;
                    if (value > 0) {
                        return '<a href="javascript:void(0);" class="consignment-link text-primary fw-bold text-decoration-none" data-sku="' + sku + '" style="cursor: pointer;">' + value + '</a>';
                    }
                    return value || 0;
                }
            };
            colModel[wj] = consignmentStockColumn;
            wj = wj+1;

            // Damaged Stock column
            let damagedStockColumn = {
                title: "Damaged Stock",
                dataIndx: "damaged_stock",
                width: 150,
                dataType: "integer",
                align: "center",
                halign: "center",
                cls: 'damaged-stock-cell',
                editable: false,
                filter: { crules: [{ condition: 'equal' }] },
                render: function(ui) {
                    let value = ui.cellData;
                    let sku = ui.rowData.sku;
                    if (value > 0) {
                        return '<a href="javascript:void(0);" class="damaged-link text-danger fw-bold text-decoration-none" data-sku="' + sku + '" style="cursor: pointer;">' + value + '</a>';
                    }
                    return value || 0;
                }
            };
            colModel[wj] = damagedStockColumn;
            wj = wj+1;

            // Toolbar configuration (matching old system)
            var toolbar = {
                cls: 'pq-toolbar-export',
                items: [
                    {
                        type: 'select',
                        label: 'Format: ',
                        attr: 'id="export_format"',
                        options: [{xlsx: 'Excel', csv: 'Csv', htm: 'Html'}]
                    },
                    {
                        type: 'button',
                        label: " Export",
                        cls: "btn btn-primary",
                        listener: function () {
                            var format = $("#export_format").val(),
                                blob = this.exportData({
                                    format: format,
                                    nopqdata: true,
                                    render: true
                                });
                            if (typeof blob === "string") {
                                blob = new Blob([blob]);
                            }
                            saveAs(blob, "Inventory." + format);
                        }
                    },
                    {type: 'separator'},
                    {
                        type: 'textbox',
                        label: "Search: ",
                        attr: 'placeholder="Enter text"',
                        listener: {
                            timeout: function (evt) {
                                var txt = $(evt.target).val();
                                var rules = this.getCMPrimary().map(function (colModel) {
                                    return {
                                        dataIndx: colModel.dataIndx,
                                        condition: 'contain',
                                        value: txt
                                    }
                                })
                                this.filter({
                                    mode: 'OR',
                                    rules: rules
                                })
                            }
                        }
                    }
                ]
            };

            // pqGrid configuration object (matching old system structure)
            var obj = {
                width: "100%",  // Full width
                height: 700,    // Fixed height to prevent double scroll
                title: "Inventory Grid - " + allWarehouses.length + " Warehouses",
                scrollModel: { horizontal: true, autoFit: false },  // Enable horizontal scroll, don't auto-fit
                numberCell: { show: true, title: "#" },
                colModel: colModel,
                dataModel: { 
                    dataType: "JSON",
                    recIndx: "id",  // Required for change tracking
                    data: data 
                },
                toolbar: toolbar,
                filterModel: { 
                    on: true, 
                    mode: "AND", 
                    header: true 
                },
                editable: true,
                editor: { select: true },
                editModel: {
                    saveKey: $.ui.keyCode.ENTER,
                    keyUpDown: false,
                    cellBorderWidth: 0
                },
                pageModel: { type: "local", rPP: 50, rPPOptions: [20, 50, 100, 250, 500, 1000], curPage: 1, strRpp: "Rows per page: {0}", strDisplay: "Showing {0} - {1} of {2}", strPage: "Page {0} of {1}" },
                resizable: true,
                rowBorders: true,
                columnBorders: true,
                freezeCols: 2,  // Freeze SKU and Product Name columns
                wrap: false,
                hwrap: false,
                trackModel: { on: true },  // Track changes for save functionality
                historyModel: { on: true },
                track: true,
                // Enable copy/paste for Excel-like functionality
                selectionModel: { type: 'cell', mode: 'block' },
                copyModel: { on: true },
                change: function (evt, ui) {
                    // Ignore checkbox selection changes - they're not data edits
                    if (ui.source === 'checkbox') return;

                    console.log('📝 Grid changed:', ui);
                    
                    // Debounce save for multiple rapid changes (like paste operations)
                    clearTimeout(window.gridSaveTimeout);
                    window.gridSaveTimeout = setTimeout(function() {
                        saveChanges();
                    }, 300);
                }
            };

            // Initialize pqGrid
            $("#grid_json_inventory").pqGrid(obj);
            
            // Get pqGrid instance
            grid = $("#grid_json_inventory").pqGrid("instance");

            console.log('✅ pqGrid initialized with ' + data.length + ' rows and ' + colModel.length + ' columns');
            console.log('✅ Grid instance:', grid);

            // Save Changes button click handler
            $('#save-changes-btn').on('click', function() {
                console.log('💾 Save Changes button clicked');
                saveChanges();
            });

            // Import button click handler
            $('#import-inv-btn').on('click', function() {
                $('#import-product-inventory').modal('show');
            });

            // Auto-save every 2 minutes (optional - matching old system pattern)
            // interval = setInterval(saveChanges, 120000);

            // ============================================
            // CONSIGNMENT STOCK MODAL CLICK HANDLER
            // ============================================
            $(document).on('click', '.consignment-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                let sku = $(this).data('sku');
                loadConsignmentModal(sku);
            });

            // ============================================
            // INCOMING STOCK MODAL CLICK HANDLER
            // ============================================
            $(document).on('click', '.incoming-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                let sku = $(this).data('sku');
                let warehouse = $(this).data('warehouse');
                loadIncomingModal(sku, warehouse);
            });

            // ============================================
            // DAMAGED STOCK MODAL CLICK HANDLER
            // ============================================
            $(document).on('click', '.damaged-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                let sku = $(this).data('sku');
                loadDamagedModal(sku);
            });
        });

        // ============================================
        // LOAD CONSIGNMENT STOCK MODAL FUNCTION
        // ============================================
        function loadConsignmentModal(sku) {
            // Set SKU in modal title
            $('#consignmentSku').text(sku);
            
            // Show loading, hide content
            $('#consignmentLoading').show();
            $('#consignmentContent').hide();
            $('#consignmentEmpty').hide();
            
            // Open modal
            let modal = new bootstrap.Modal(document.getElementById('consignmentModal'));
            modal.show();
            
            // URL encode the SKU to handle special characters
            let encodedSku = encodeURIComponent(sku);
            
            // Fetch data via AJAX
            $.ajax({
                url: '/admin/api/inventory/sku/' + encodedSku + '/consignments',
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#consignmentLoading').hide();
                    
                    if (response.length > 0) {
                        let tableBody = '';
                        response.forEach(function(item) {
                            tableBody += '<tr>';
                            tableBody += '<td><a href="/admin/customers/' + item.customer_id + '" target="_blank" class="text-decoration-none">' + item.customer + '</a></td>';
                            tableBody += '<td class="text-center">' + item.quantity_sent + '</td>';
                            tableBody += '<td class="text-center"><span class="badge bg-success">' + item.quantity_sold + '</span></td>';
                            tableBody += '<td class="text-center"><span class="badge bg-warning">' + item.quantity_returned + '</span></td>';
                            tableBody += '<td class="text-center"><strong><span class="badge bg-primary">' + item.available_qty + '</span></strong></td>';
                            tableBody += '<td class="text-center">' + item.date_consigned + '</td>';
                            tableBody += '<td class="text-center"><a href="/admin/consignments/' + item.consignment_id + '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a></td>';
                            tableBody += '</tr>';
                        });
                        
                        $('#consignmentTableBody').html(tableBody);
                        $('#consignmentContent').show();
                    } else {
                        $('#consignmentEmpty').show();
                        $('#consignmentContent').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#consignmentLoading').hide();
                    $('#consignmentTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading data: ' + error + '</td></tr>');
                    $('#consignmentContent').show();
                }
            });
        }

        // ============================================
        // LOAD INCOMING STOCK MODAL FUNCTION
        // ============================================
        function loadIncomingModal(sku, warehouse) {
            // Set SKU in modal title
            $('#incomingSku').text(sku + (warehouse ? ' (' + warehouse + ')' : ''));
            
            // Show loading, hide content
            $('#incomingLoading').show();
            $('#incomingContent').hide();
            $('#incomingEmpty').hide();
            
            // Open modal
            let modal = new bootstrap.Modal(document.getElementById('incomingModal'));
            modal.show();
            
            // URL encode the SKU to handle special characters
            let encodedSku = encodeURIComponent(sku);
            
            // Fetch data via AJAX
            $.ajax({
                url: '/admin/api/inventory/sku/' + encodedSku + '/incoming' + (warehouse ? '?warehouse=' + warehouse : ''),
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#incomingLoading').hide();
                    
                    if (response.length > 0) {
                        let tableBody = '';
                        response.forEach(function(item) {
                            tableBody += '<tr>';
                            tableBody += '<td><strong>' + item.warehouse + '</strong></td>';
                            tableBody += '<td class="text-center">' + (item.eta || 'Not Set') + '</td>';
                            tableBody += '<td class="text-center"><span class="badge bg-success">' + item.quantity + '</span></td>';
                            tableBody += '<td>' + (item.notes || '-') + '</td>';
                            tableBody += '</tr>';
                        });
                        
                        $('#incomingTableBody').html(tableBody);
                        $('#incomingContent').show();
                    } else {
                        $('#incomingEmpty').show();
                        $('#incomingContent').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#incomingLoading').hide();
                    $('#incomingTableBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading data: ' + error + '</td></tr>');
                    $('#incomingContent').show();
                }
            });
        }

        // ============================================
        // LOAD DAMAGED STOCK MODAL FUNCTION
        // ============================================
        function loadDamagedModal(sku) {
            // Set SKU in modal title
            $('#damagedSku').text(sku);
            
            // Show loading, hide content
            $('#damagedLoading').show();
            $('#damagedContent').hide();
            $('#damagedEmpty').hide();
            
            // Open modal
            let modal = new bootstrap.Modal(document.getElementById('damagedModal'));
            modal.show();
            
            // URL encode the SKU
            let encodedSku = encodeURIComponent(sku);
            
            // Fetch data via AJAX
            $.ajax({
                url: '/admin/api/inventory/sku/' + encodedSku + '/damaged',
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#damagedLoading').hide();
                    
                    if (response.length > 0) {
                        let tableBody = '';
                        response.forEach(function(item) {
                            tableBody += '<tr>';
                            tableBody += '<td><strong>' + item.warehouse + ' (' + item.warehouse_code + ')</strong></td>';
                            tableBody += '<td class="text-center"><span class="badge bg-danger">' + item.quantity + '</span></td>';
                            tableBody += '<td class="text-center"><strong>' + item.condition + '</strong></td>';
                            tableBody += '<td>' + item.notes + '</td>';
                            tableBody += '<td class="text-center">' + item.date_recorded + '</td>';
                            tableBody += '</tr>';
                        });
                        
                        $('#damagedTableBody').html(tableBody);
                        $('#damagedContent').show();
                    } else {
                        $('#damagedEmpty').show();
                        $('#damagedContent').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#damagedLoading').hide();
                    $('#damagedTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading data: ' + error + '</td></tr>');
                    $('#damagedContent').show();
                }
            });
        }

        // Show processing loader on form submit (Tunerstop style)
        $(document).ready(function() {
            $('#inventoryImportForm').on('submit', function(e) {
                // Show the processing overlay
                $('#processingOverlay').css('display', 'flex');
                
                // Disable the submit button to prevent double submission
                $('#importInventoryBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
                
                // Hide the modal
                $('#import-product-inventory').modal('hide');
                
                console.log('📤 Inventory import form submitted - showing loader');
                
                // Form will submit normally (no e.preventDefault())
                // The loader will stay visible until the page reloads with results
            });

            // Export button handler
            $('#export-btn').on('click', function() {
                if (typeof grid !== 'undefined' && grid) {
                    var format = 'xlsx';
                    var blob = grid.exportData({
                        format: format,
                        nopqdata: true,
                        render: true
                    });
                    if (typeof blob === "string") {
                        blob = new Blob([blob]);
                    }
                    saveAs(blob, "Inventory-Export-" + new Date().toISOString().slice(0,10) + "." + format);
                    console.log('✅ Inventory exported as ' + format);
                } else {
                    alert('Grid is not ready. Please wait for the page to load completely.');
                }
            });
        });
    </script>

    <!-- Consignment Stock Modal -->
    <div class="modal fade" id="consignmentModal" tabindex="-1" aria-labelledby="consignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="consignmentModalLabel">
                        <i class="bi bi-box-seam"></i> Consignment Stock - <span id="consignmentSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="consignmentLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading consignment details...</p>
                    </div>
                    <div id="consignmentContent" style="display: none;">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Available Qty</strong> = Sent - Sold - Returned
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Customer</th>
                                        <th class="text-center">Sent</th>
                                        <th class="text-center">Sold</th>
                                        <th class="text-center">Returned</th>
                                        <th class="text-center"><strong>Available</strong></th>
                                        <th class="text-center">Date Consigned</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="consignmentTableBody">
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div id="consignmentEmpty" class="alert alert-warning" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i> No active consignments found for this product.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Incoming Stock Modal -->
    <div class="modal fade" id="incomingModal" tabindex="-1" aria-labelledby="incomingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="incomingModalLabel">
                        <i class="bi bi-truck"></i> Incoming Stock - <span id="incomingSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="incomingLoading" class="text-center py-5">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading incoming stock details...</p>
                    </div>
                    <div id="incomingContent" style="display: none;">
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Incoming Stock</strong> shows expected deliveries and quantities in transit
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Warehouse</th>
                                        <th class="text-center">ETA Date</th>
                                        <th class="text-center">Quantity</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="incomingTableBody">
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div id="incomingEmpty" class="alert alert-warning" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i> No incoming stock found for this product.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Damaged Stock Modal -->
    <div class="modal fade" id="damagedModal" tabindex="-1" aria-labelledby="damagedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="damagedModalLabel">
                        <i class="bi bi-exclamation-octagon"></i> Damaged Stock - <span id="damagedSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="damagedLoading" class="text-center py-5">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading damaged stock details...</p>
                    </div>
                    <div id="damagedContent" style="display: none;">
                        <div class="alert alert-danger mb-3">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Damaged Stock</strong> tracks items returned as damaged or defective. These are NOT part of main saleable stock.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Warehouse</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-center">Condition</th>
                                        <th>Notes</th>
                                        <th class="text-center">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="damagedTableBody">
                                    <!-- Data loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div id="damagedEmpty" class="alert alert-warning" style="display: none;">
                            <i class="bi bi-exclamation-triangle"></i> No damaged stock found for this product.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Bulk Transfer Scripts -->
    <script>
        $(document).ready(function() {
            // Bulk Transfer Button Click
            $('#bulk-transfer-btn').on('click', function() {
                if (typeof grid === 'undefined' || !grid) {
                    showToast('Grid is not ready yet.', 'error');
                    return;
                }

                // Get ALL rows across all pages (not just current page)
                var allData = grid.option('dataModel.data');
                var selectedIds = [];
                if (allData && allData.length > 0) {
                    allData.forEach(function(row) {
                        if (row.state === true) {
                            selectedIds.push(row.id);
                        }
                    });
                }

                // Populate warehouse dropdowns from JS allWarehouses variable
                var $src = $('#source_warehouse_id').empty().append('<option value="">-- Select source --</option>');
                var $dst = $('#destination_warehouse_id').empty().append('<option value="">-- Select destination --</option>');
                if (typeof allWarehouses !== 'undefined') {
                    allWarehouses.forEach(function(wh) {
                        $src.append('<option value="' + wh.id + '">' + wh.warehouse_name + ' (' + wh.code + ')</option>');
                        $dst.append('<option value="' + wh.id + '">' + wh.warehouse_name + ' (' + wh.code + ')</option>');
                    });
                }

                if (selectedIds.length === 0) {
                    $('#transfer_count_msg')
                        .removeClass('alert-info alert-success')
                        .addClass('alert-danger')
                        .html('<i class="bi bi-exclamation-triangle"></i> Please tick the checkboxes on the left to select items first.');
                    $('#confirmBulkTransferBtn').prop('disabled', true);
                } else {
                    $('#transfer_count_msg')
                        .removeClass('alert-danger alert-info')
                        .addClass('alert-success')
                        .html('<i class="bi bi-check-circle"></i> <strong>' + selectedIds.length + '</strong> item(s) selected for transfer.');
                    $('#confirmBulkTransferBtn').prop('disabled', false);
                    $('#transfer_selected_ids').val(JSON.stringify(selectedIds));
                }

                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('bulk-transfer-modal'));
                modal.show();
            });
        });
    </script>
</x-filament-panels::page>
