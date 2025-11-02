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
    
    <style>
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
            min-height: 600px;
            width: 100% !important;
            overflow-x: auto !important;
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

        /* HIDE FILTER ICON/ARROW - This was the black arrow you wanted to hide */
        .pq-grid-header-search-icon,
        .pq-grid-col .ui-icon-triangle-1-s,
        .pq-grid-col .ui-icon-carat-2-n-s,
        .pq-grid-title-row .ui-icon,
        .pq-grid-col .ui-icon,
        .ui-icon-triangle-1-n,
        .ui-icon-triangle-1-s,
        .ui-icon-triangle-2-n-s,
        .pq-grid-title-row .pq-grid-col .ui-icon,
        .pq-grid-title-row .ui-state-default .ui-icon,
        span.ui-icon {
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
        var interval;
        var grid;

        // Setup CSRF token
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

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

            console.log('✅ Edit cell saved');
            console.log('Active AJAX:', $.active);
            console.log('Is Dirty:', grid.isDirty());
            
            var validationResult = grid.isValidChange({ allowInvalid: true });
            console.log('Validation:', validationResult);

            if (!$.active && grid.isDirty() && validationResult.valid) {
                var gridChanges = grid.getChanges({ format: 'byVal' });
                console.log('📦 Grid changes:', gridChanges);

                $.ajax({
                    dataType: "json",
                    type: "POST",
                    async: true,
                    beforeSend: function (jqXHR, settings) {
                        console.log('🚀 Sending AJAX request...');
                        grid.option("strLoading", "Saving..");
                        grid.showLoading();
                    },
                    url: "/admin/inventory/save-batch",
                    data: { list: gridChanges },
                    success: function (changes) {
                        console.log('✅ AJAX Success:', changes);
                        grid.history({method: 'reset'});
                        grid.commit({ type: 'add', rows: changes.addList });
                        grid.commit({ type: 'update', rows: changes.updateList });
                        grid.commit({ type: 'delete', rows: changes.deleteList });
                        
                        console.log('✅ Inventory saved successfully!');
                        // Removed alert for auto-save (too intrusive)
                    },
                    complete: function (resp) {
                        console.log('🏁 AJAX Complete');
                        grid.hideLoading();
                        grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('❌ AJAX Error:', {
                            status: jqXHR.status,
                            textStatus: textStatus,
                            errorThrown: errorThrown,
                            response: jqXHR.responseText
                        });
                        
                        var errorMessage = "Failed to save inventory.";
                        if (jqXHR.responseJSON && jqXHR.responseJSON.errors) {
                            errorMessage = jqXHR.responseJSON.errors.join("\n");
                        } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                            errorMessage = jqXHR.responseJSON.message;
                        }
                        
                        alert('❌ Error: ' + errorMessage);
                    }
                });
            } else {
                console.log('⚠️ No changes or validation failed');
                if ($.active) {
                    alert('⏳ An AJAX request is already in progress. Please wait...');
                } else if (!grid.isDirty()) {
                    alert('ℹ️ No changes to save.');
                } else {
                    alert('❌ Validation errors exist. Please fix them before saving.');
                }
            }
        }

        $(document).ready(function () {
            // Prepare data - EXACT structure from old Reporting system (lines 269-274)
            var data = [];
            api_data.forEach(function(element, index) {
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
            var wj = 5;
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
                    filter: { crules: [{ condition: 'equal' }] }
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
                    if (value > 0) {
                        return '<span style="color: #0066cc; font-weight: bold;">' + value + '</span>';
                    }
                    return value || 0;
                }
            };
            colModel[wj] = consignmentStockColumn;
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
                width: "auto",  // Auto width to show all columns
                height: 650,
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
                pageModel: { type: "local", rPP: 100, rPPOptions: [20, 50, 100, 500, 1000] },
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
        });

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
</x-filament-panels::page>
