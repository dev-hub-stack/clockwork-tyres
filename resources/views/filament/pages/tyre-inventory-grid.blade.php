@once
    @push('styles')
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .fi-body, .fi-main, .fi-content, .fi-section-content-ctn { max-width: none !important; width: 100% !important; }
        .page-content { background: #fff; border-radius: 12px; padding: 1.5rem; }
        .summary-shell { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr); padding: 1.5rem; border: 1px solid #e5e7eb; border-radius: 16px; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); margin-bottom: 1.5rem; }
        .summary-copy .eyebrow, .summary-metric .label { font-size: .8rem; text-transform: uppercase; letter-spacing: .14em; font-weight: 700; color: #64748b; }
        .summary-copy h2 { margin: .35rem 0 .75rem; font-size: 2rem; font-weight: 800; color: #0f172a; }
        .summary-copy p { margin: 0; color: #475569; line-height: 1.7; }
        .summary-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .summary-metric { background: #fff; border: 1px solid #dbeafe; border-radius: 14px; padding: 1rem; min-height: 110px; }
        .summary-metric .value { display: block; margin-top: .45rem; font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1.15; }
        .summary-metric .meta { display: block; margin-top: .35rem; color: #64748b; font-size: .85rem; }
        .action-buttons { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
        .grid-scroll-wrapper { overflow-x: auto; width: 100%; }
        #grid_json_inventory, .pq-grid { width: 100% !important; }
        .pq-grid-cont { width: 100% !important; }
        @media (max-width: 1280px) { .summary-shell { grid-template-columns: 1fr; } }
        @media (max-width: 1024px) { #grid_json_inventory, .pq-grid { min-width: 1500px !important; width: auto !important; } }
        .inventory-cell { background-color: #ecfdf5 !important; font-weight: 600; }
        .inventory-cell-eta { background-color: #fff7ed !important; }
        .inventory-cell-incoming { background-color: #eff6ff !important; }
        .pq-grid-header-search-row { display: table-row !important; visibility: visible !important; background-color: #f8fafc !important; }
        .pq-grid-header-search-row .pq-grid-col { padding: 6px !important; background: #f8fafc !important; border-color: #e5e7eb !important; }
        .pq-grid-hd-search-field { display: block !important; width: 100% !important; padding: 5px 8px !important; border: 1px solid #d1d5db !important; border-radius: 6px !important; font-size: 13px !important; background: #fff !important; color: #111827 !important; }
        .pq-grid-col .ui-icon, .pq-grid-title-row .ui-icon { display: none !important; }
        .pq-grid-col, .pq-grid-number-cell { border-color: #e5e7eb !important; background-color: #f3f4f6 !important; }
        .pq-grid-title-row .pq-grid-number-cell, .pq-grid-header-search-row .pq-grid-number-cell, .pq-grid-title-row .ui-state-default { background-color: #374151 !important; color: #fff !important; }
        .pq-grid-row.pq-striped { background: #f9fafb; }
        .pq-pager { display: flex !important; align-items: center !important; flex-wrap: wrap !important; gap: .35rem !important; padding: .5rem .75rem !important; background: #f8fafc !important; border-top: 1px solid #e5e7eb !important; }
        .tyre-thumb { width: 42px; height: 42px; object-fit: cover; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; }
        .modal .table th { font-size: .75rem; letter-spacing: .1em; text-transform: uppercase; color: #64748b; }
    </style>
    @endpush

    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    @endpush
@endonce

<div>
    <div class="page-content">
        <div class="summary-shell">
            <div class="summary-copy">
                <span class="eyebrow">Tyre inventory operations</span>
                <h2>Tyre Inventory Grid</h2>
                <p>Manage tyre stock by warehouse using the same CRM grid interaction pattern as the product inventory page. Quantities, ETA, inbound stock, transfers, imports, and movement logs all stay scoped to the current business account.</p>
            </div>
            <div class="summary-metrics">
                <div class="summary-metric">
                    <span class="label">Current Business</span>
                    <span class="value">{{ $this->currentAccountSummary['name'] ?? 'No active account' }}</span>
                    <span class="meta">{{ $this->currentAccountSummary['account_type'] ? str($this->currentAccountSummary['account_type'])->headline() : 'No business type' }}</span>
                </div>
                <div class="summary-metric">
                    <span class="label">Plan</span>
                    <span class="value">{{ $this->currentAccountSummary['plan'] ? str($this->currentAccountSummary['plan'])->headline() : 'N/A' }}</span>
                    <span class="meta">{{ $this->hasInventoryEntitlement ? 'Own tyre inventory enabled' : 'Own tyre inventory locked on this plan' }}</span>
                </div>
                <div class="summary-metric">
                    <span class="label">Warehouses</span>
                    <span class="value">{{ count($this->warehouses) }}</span>
                    <span class="meta">Editable warehouse columns are generated from the current business account.</span>
                </div>
                <div class="summary-metric">
                    <span class="label">Movement Log</span>
                    <span class="value">Tyres</span>
                    <span class="meta">Shared log engine, prefiltered to tyre movements.</span>
                </div>
            </div>
        </div>

        @unless($this->hasInventoryEntitlement)
            <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4 mt-1"></i>
                <div>
                    <strong>Tyre inventory editing is not enabled on the current plan.</strong><br>
                    You can still review the tyre inventory grid, but save, import, add inventory, and transfer actions are locked until this business account has own inventory access.
                </div>
            </div>
        @endunless

        <div class="action-buttons">
            @if($this->canBulkTransfer && $this->hasInventoryEntitlement)
                <button type="button" class="btn btn-warning" id="bulk-transfer-btn"><i class="bi bi-arrow-left-right"></i> Bulk Transfer</button>
            @endif
            @if($this->canAddInventory && $this->hasInventoryEntitlement)
                <button type="button" class="btn btn-success" id="add-inventory-btn"><i class="bi bi-plus-circle"></i> Add Inventory</button>
            @endif
            @if($this->canEditCells && $this->hasInventoryEntitlement)
                <button type="button" class="btn btn-primary" id="import-inv-btn"><i class="bi bi-cloud-upload"></i> Import Inventory</button>
            @endif
            <button type="button" class="btn btn-info" id="export-btn" onclick="exportInventoryGrid()"><i class="bi bi-file-earmark-arrow-down"></i> Export CSV</button>
            <a href="/admin/inventory-movement-log?inventory_type=tyres" class="btn btn-secondary" id="view-log-btn"><i class="bi bi-clock-history"></i> Movement Log</a>
            @if($this->canEditCells && $this->hasInventoryEntitlement)
                <button type="button" class="btn btn-dark" id="save-changes-btn"><i class="bi bi-save"></i> Save Changes</button>
            @endif
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="grid-scroll-wrapper">
            <div id="grid_json_inventory"></div>
        </div>
    <datalist id="tyre-sku-options"></datalist>

    <div class="modal fade" id="bulk-transfer-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Transfer Tyre Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>SKU</th><th>From</th><th class="text-center">Available</th><th class="text-center">Transfer Qty</th><th>To</th><th></th></tr></thead>
                            <tbody id="bulk-transfer-rows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="bt-add-line-btn">Add Line</button>
                    <button type="button" class="btn btn-primary" id="bt-transfer-btn">Transfer Stock</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="add-inventory-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Tyre Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>SKU</th><th class="text-center">Add Qty</th><th>To</th><th></th></tr></thead>
                            <tbody id="add-inventory-rows"></tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <label class="form-label fw-semibold">Reference / Bill / PO Number</label>
                        <input type="text" id="ai-reference" class="form-control" placeholder="Enter Reference/Bill/PO Number...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="ai-add-line-btn">Add Line</button>
                    <button type="button" class="btn btn-primary" id="ai-submit-btn">Add Inventory</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="import-tyre-inventory-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Tyre Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.tyres.inventory.import') }}" method="POST" enctype="multipart/form-data" id="inventoryImportForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="importFile" class="form-label">Inventory file</label>
                            <input type="file" id="importFile" name="importFile" class="form-control" accept=".csv,.xlsx,.txt" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-3 rounded border p-3 bg-light">
                            <div>
                                <strong>Need a template?</strong>
                                <div class="text-muted small">Download a CSV with your current business warehouses as columns.</div>
                            </div>
                            <a href="{{ route('admin.tyres.inventory.template') }}" class="btn btn-warning"><i class="bi bi-download"></i> Download Template</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary" id="importInventoryBtn">Import</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="processingOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.72);z-index:9999;align-items:center;justify-content:center;">
        <div class="bg-white rounded-4 shadow p-5 text-center">
            <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"></div>
            <h4 class="mt-3 mb-2">Processing import...</h4>
            <p class="text-muted mb-0">Please wait while the tyre inventory file is applied.</p>
        </div>
    </div>

    <div class="modal fade" id="consignmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Consignment Stock - <span id="consignmentSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="consignmentLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">Loading consignment details...</p>
                    </div>
                    <div id="consignmentContent" style="display:none;">
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
                                <tbody id="consignmentTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="consignmentEmpty" class="text-center py-5" style="display:none;">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="mt-3 text-muted mb-0">No consignments found for this tyre.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incomingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-truck"></i> Incoming Stock - <span id="incomingSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="incomingLoading" class="text-center py-5">
                        <div class="spinner-border text-success" role="status"></div>
                        <p class="mt-3 text-muted">Loading incoming stock details...</p>
                    </div>
                    <div id="incomingContent" style="display:none;">
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Incoming Stock</strong> shows ETA and quantity in transit to the selected warehouse.
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
                                <tbody id="incomingTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="incomingEmpty" class="text-center py-5" style="display:none;">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="mt-3 text-muted mb-0">No incoming stock found for this tyre.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="damagedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-octagon"></i> Damaged Stock - <span id="damagedSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="damagedLoading" class="text-center py-5">
                        <div class="spinner-border text-danger" role="status"></div>
                        <p class="mt-3 text-muted">Loading damaged stock details...</p>
                    </div>
                    <div id="damagedContent" style="display:none;">
                        <div class="alert alert-danger mb-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Damaged Stock</strong> reflects warranty/return-driven non-saleable tyre quantities.
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
                                <tbody id="damagedTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="damagedEmpty" class="text-center py-5" style="display:none;">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="mt-3 text-muted mb-0">No damaged stock found for this tyre.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
    <script>
        const gridEndpoint = '{{ route('admin.api.tyres.inventory.grid-data') }}';
        const saveEndpoint = '{{ route('admin.tyres.inventory.save-batch') }}';
        const exportEndpoint = '{{ route('admin.tyres.inventory.export-csv') }}';
        const bulkTransferEndpoint = '{{ route('admin.tyres.inventory.bulk-transfer') }}';
        const addInventoryEndpoint = '{{ route('admin.tyres.inventory.add') }}';
        const consignmentEndpointTemplate = '{{ route('admin.api.tyres.inventory.consignments.bySku', ['sku' => '__SKU__']) }}';
        const incomingEndpointTemplate = '{{ route('admin.api.tyres.inventory.incoming.bySku', ['sku' => '__SKU__']) }}';
        const damagedEndpointTemplate = '{{ route('admin.api.tyres.inventory.damaged.bySku', ['sku' => '__SKU__']) }}';
        const allWarehouses = @json($this->warehouses);
        const canEditCells = @json($this->canEditCells && $this->hasInventoryEntitlement);
        const canBulkTransfer = @json($this->canBulkTransfer && $this->hasInventoryEntitlement);
        const canAddInventory = @json($this->canAddInventory && $this->hasInventoryEntitlement);
        const csrfToken = @json(csrf_token());

        let grid = null;
        let tyreRows = [];
    </script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });

        function showToast(message, type = 'success') {
            Toast.fire({ icon: type, title: message });
        }

        function escaped(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatMoney(value) {
            const numeric = parseFloat(value ?? 0);
            if (Number.isNaN(numeric)) {
                return '0.00';
            }

            return numeric.toFixed(2);
        }

        function imageMarkup(url, label) {
            if (!url) {
                return '<div class="tyre-thumb d-inline-flex align-items-center justify-content-center text-muted small">N/A</div>';
            }

            return '<img src="' + escaped(url) + '" alt="' + escaped(label) + '" class="tyre-thumb">';
        }

        function updateSkuOptions() {
            const options = tyreRows.map(function (row) {
                return '<option value="' + escaped(row.sku) + '"></option>';
            }).join('');

            $('#tyre-sku-options').html(options);
        }

        function hydrateTyreRows(rows) {
            tyreRows = (rows || []).map(function (row) {
                const hydrated = Object.assign({ state: false }, row);

                (row.inventory || []).forEach(function (inventory) {
                    hydrated['qty' + inventory.warehouse_id] = parseInt(inventory.quantity || 0, 10);
                    hydrated['eta' + inventory.warehouse_id] = inventory.eta || '';
                    hydrated['e_ta_q_ty' + inventory.warehouse_id] = parseInt(inventory.eta_qty || 0, 10);
                });

                allWarehouses.forEach(function (warehouse) {
                    hydrated['qty' + warehouse.id] = parseInt(hydrated['qty' + warehouse.id] || 0, 10);
                    hydrated['eta' + warehouse.id] = hydrated['eta' + warehouse.id] || '';
                    hydrated['e_ta_q_ty' + warehouse.id] = parseInt(hydrated['e_ta_q_ty' + warehouse.id] || 0, 10);
                });

                hydrated.current_stock = parseInt(hydrated.current_stock || 0, 10);
                hydrated.incoming_stock = parseInt(hydrated.incoming_stock || 0, 10);
                hydrated.consignment_stock = parseInt(hydrated.consignment_stock || 0, 10);
                hydrated.damaged_stock = parseInt(hydrated.damaged_stock || 0, 10);

                return hydrated;
            });

            updateSkuOptions();

            return tyreRows;
        }

        function currentGridData() {
            if (!grid) {
                return tyreRows;
            }

            return grid.option('dataModel.data') || [];
        }

        function selectedRows() {
            return currentGridData().filter(function (row) {
                return row.state === true;
            });
        }

        function findTyreRowBySku(sku) {
            return currentGridData().find(function (row) {
                return String(row.sku).toUpperCase() === String(sku).trim().toUpperCase();
            });
        }

        function availabilityForOffer(offerId, sourceValue) {
            const row = currentGridData().find(function (entry) {
                return String(entry.id) === String(offerId);
            });

            if (!row || !sourceValue) {
                return 0;
            }

            if (String(sourceValue).indexOf('incoming_') === 0) {
                const warehouseId = String(sourceValue).replace('incoming_', '');
                return parseInt(row['e_ta_q_ty' + warehouseId] || 0, 10);
            }

            return parseInt(row['qty' + sourceValue] || 0, 10);
        }

        function buildWarehouseOptions(selected = '', includeIncoming = true) {
            let html = '<option value="">Select warehouse</option>';

            allWarehouses.forEach(function (warehouse) {
                const isSelected = String(selected) === String(warehouse.id) ? ' selected' : '';
                html += '<option value="' + warehouse.id + '"' + isSelected + '>' + escaped(warehouse.code) + ' - ' + escaped(warehouse.warehouse_name) + '</option>';
            });

            if (includeIncoming) {
                allWarehouses.forEach(function (warehouse) {
                    const value = 'incoming_' + warehouse.id;
                    const isSelected = String(selected) === value ? ' selected' : '';
                    html += '<option value="' + value + '"' + isSelected + '>Incoming - ' + escaped(warehouse.code) + '</option>';
                });
            }

            return html;
        }

        function qtySpinner(inputClass) {
            return '<div class="d-inline-flex align-items-center gap-2">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary qty-dec">-</button>'
                + '<input type="number" class="form-control form-control-sm text-center ' + inputClass + '" value="0" min="0" style="width:88px;">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary qty-inc">+</button>'
                + '</div>';
        }

        function buildTransferRow(rowData = null, index = 0) {
            const offerId = rowData ? rowData.id : '';
            const sku = rowData ? rowData.sku : '';

            return '<tr data-offer-id="' + escaped(offerId) + '">'
                + '<td><input type="text" class="form-control bt-sku" list="tyre-sku-options" value="' + escaped(sku) + '" placeholder="Type SKU..."></td>'
                + '<td><select class="form-select bt-from">' + buildWarehouseOptions('', true) + '</select></td>'
                + '<td class="text-center align-middle"><span class="bt-available text-muted">0</span></td>'
                + '<td class="text-center align-middle">' + qtySpinner('bt-qty') + '</td>'
                + '<td><select class="form-select bt-to">' + buildWarehouseOptions('', false) + '</select></td>'
                + '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger bt-remove">&times;</button></td>'
                + '</tr>';
        }

        function buildAddRow(rowData = null, index = 0) {
            const offerId = rowData ? rowData.id : '';
            const sku = rowData ? rowData.sku : '';

            return '<tr data-offer-id="' + escaped(offerId) + '">'
                + '<td><input type="text" class="form-control ai-sku" list="tyre-sku-options" value="' + escaped(sku) + '" placeholder="Type SKU..."></td>'
                + '<td class="text-center align-middle">' + qtySpinner('ai-qty') + '</td>'
                + '<td><select class="form-select ai-to">' + buildWarehouseOptions('', true) + '</select></td>'
                + '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger ai-remove">&times;</button></td>'
                + '</tr>';
        }

        function consignmentUrl(sku) {
            return consignmentEndpointTemplate.replace('__SKU__', encodeURIComponent(sku));
        }

        function incomingUrl(sku, warehouseCode = null) {
            let url = incomingEndpointTemplate.replace('__SKU__', encodeURIComponent(sku));

            if (warehouseCode) {
                url += '?warehouse=' + encodeURIComponent(warehouseCode);
            }

            return url;
        }

        function damagedUrl(sku) {
            return damagedEndpointTemplate.replace('__SKU__', encodeURIComponent(sku));
        }

        function exportInventoryGrid() {
            window.location.href = exportEndpoint;
        }

        function saveChanges() {
            if (!grid) {
                showToast('Grid is still loading.', 'warning');
                return false;
            }

            if (grid.saveEditCell() === false) {
                return false;
            }

            if (!grid.isDirty()) {
                showToast('No changes to save.', 'info');
                return false;
            }

            const gridChanges = grid.getChanges({ format: 'byVal' });

            if (gridChanges && gridChanges.updateList) {
                gridChanges.updateList = gridChanges.updateList.map(function (row) {
                    const cloned = Object.assign({}, row);
                    delete cloned.state;
                    return cloned;
                });
            }

            $.ajax({
                url: saveEndpoint,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ list: gridChanges }),
                dataType: 'json',
                beforeSend: function () {
                    grid.option('strLoading', 'Saving...');
                    grid.showLoading();
                },
                success: function (response) {
                    grid.history({ method: 'reset' });
                    grid.commit({ type: 'add', rows: response.addList || [] });
                    grid.commit({ type: 'update', rows: response.updateList || [] });
                    grid.commit({ type: 'delete', rows: response.deleteList || [] });
                    showToast(response.message || 'Tyre inventory saved successfully.');
                },
                error: function (xhr) {
                    showToast(xhr.responseJSON?.message || 'Failed to save tyre inventory.', 'error');
                },
                complete: function () {
                    grid.hideLoading();
                    grid.option('strLoading', $.paramquery.pqGrid.defaults.strLoading);
                },
            });

            return true;
        }

        function loadConsignmentModal(sku) {
            $('#consignmentSku').text(sku);
            $('#consignmentLoading').show();
            $('#consignmentContent, #consignmentEmpty').hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('consignmentModal')).show();

            $.getJSON(consignmentUrl(sku))
                .done(function (response) {
                    $('#consignmentLoading').hide();

                    if (!response.length) {
                        $('#consignmentEmpty').show();
                        return;
                    }

                    const rows = response.map(function (item) {
                        const viewUrl = item.consignment_id ? '/admin/consignments/' + item.consignment_id : null;
                        return '<tr>'
                            + '<td><strong>' + escaped(item.customer) + '</strong></td>'
                            + '<td class="text-center">' + escaped(item.quantity_sent) + '</td>'
                            + '<td class="text-center">' + escaped(item.quantity_sold) + '</td>'
                            + '<td class="text-center">' + escaped(item.quantity_returned) + '</td>'
                            + '<td class="text-center"><span class="badge bg-primary">' + escaped(item.available_qty) + '</span></td>'
                            + '<td class="text-center">' + escaped(item.date_consigned) + '</td>'
                            + '<td class="text-center">' + (viewUrl ? '<a class="btn btn-sm btn-outline-primary" href="' + viewUrl + '">View</a>' : '-') + '</td>'
                            + '</tr>';
                    }).join('');

                    $('#consignmentTableBody').html(rows);
                    $('#consignmentContent').show();
                })
                .fail(function () {
                    $('#consignmentLoading').hide();
                    $('#consignmentEmpty').show();
                });
        }

        function loadIncomingModal(sku, warehouseCode = null) {
            $('#incomingSku').text(sku);
            $('#incomingLoading').show();
            $('#incomingContent, #incomingEmpty').hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('incomingModal')).show();

            $.getJSON(incomingUrl(sku, warehouseCode))
                .done(function (response) {
                    $('#incomingLoading').hide();

                    if (!response.length) {
                        $('#incomingEmpty').show();
                        return;
                    }

                    const rows = response.map(function (item) {
                        return '<tr>'
                            + '<td><strong>' + escaped(item.warehouse) + '</strong> <span class="text-muted">(' + escaped(item.warehouse_code) + ')</span></td>'
                            + '<td class="text-center">' + escaped(item.eta || 'Not set') + '</td>'
                            + '<td class="text-center"><span class="badge bg-success">' + escaped(item.quantity) + '</span></td>'
                            + '<td>' + escaped(item.notes || '-') + '</td>'
                            + '</tr>';
                    }).join('');

                    $('#incomingTableBody').html(rows);
                    $('#incomingContent').show();
                })
                .fail(function () {
                    $('#incomingLoading').hide();
                    $('#incomingEmpty').show();
                });
        }

        function loadDamagedModal(sku) {
            $('#damagedSku').text(sku);
            $('#damagedLoading').show();
            $('#damagedContent, #damagedEmpty').hide();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('damagedModal')).show();

            $.getJSON(damagedUrl(sku))
                .done(function (response) {
                    $('#damagedLoading').hide();

                    if (!response.length) {
                        $('#damagedEmpty').show();
                        return;
                    }

                    const rows = response.map(function (item) {
                        return '<tr>'
                            + '<td><strong>' + escaped(item.warehouse) + '</strong> <span class="text-muted">(' + escaped(item.warehouse_code) + ')</span></td>'
                            + '<td class="text-center"><span class="badge bg-danger">' + escaped(item.quantity) + '</span></td>'
                            + '<td class="text-center">' + escaped(item.condition) + '</td>'
                            + '<td>' + escaped(item.notes || '-') + '</td>'
                            + '<td class="text-center">' + escaped(item.date_recorded) + '</td>'
                            + '</tr>';
                    }).join('');

                    $('#damagedTableBody').html(rows);
                    $('#damagedContent').show();
                })
                .fail(function () {
                    $('#damagedLoading').hide();
                    $('#damagedEmpty').show();
                });
        }
    </script>
    <script>
        function inventoryStatusBadge(status) {
            const value = String(status || '').trim();
            if (!value) {
                return '<span class="badge text-bg-secondary">Pending</span>';
            }

            const map = {
                configured_in_stock: 'success',
                configured_inbound: 'info',
                configured_out_of_stock: 'warning',
                blocked_warehouse_mapping: 'secondary',
            };

            const color = map[value] || 'secondary';
            const label = value.replace(/_/g, ' ').replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });

            return '<span class="badge text-bg-' + color + '">' + escaped(label) + '</span>';
        }

        function baseColumns() {
            return [
                {
                    dataIndx: 'state',
                    title: '',
                    cb: { header: true, select: true, all: true },
                    type: 'checkbox',
                    cls: 'ui-state-default',
                    dataType: 'bool',
                    editor: false,
                    resizable: false,
                    width: 40,
                    minWidth: 40,
                    maxWidth: 40,
                    sortable: false,
                    filter: { crules: [] },
                },
                {
                    title: 'Image',
                    dataIndx: 'image',
                    width: 90,
                    minWidth: 90,
                    editable: false,
                    sortable: false,
                    filter: false,
                    align: 'center',
                    render: function (ui) {
                        return imageMarkup(ui.rowData.image, ui.rowData.model || ui.rowData.sku);
                    },
                },
                {
                    title: 'SKU',
                    dataIndx: 'sku',
                    width: 180,
                    dataType: 'string',
                    editable: false,
                    filter: { crules: [{ condition: 'begin' }] },
                },
                {
                    title: 'Brand',
                    dataIndx: 'brand',
                    width: 140,
                    editable: false,
                    filter: { crules: [{ condition: 'begin' }] },
                },
                {
                    title: 'Model',
                    dataIndx: 'model',
                    width: 220,
                    editable: false,
                    filter: { crules: [{ condition: 'begin' }] },
                },
                {
                    title: 'Full Size',
                    dataIndx: 'full_size',
                    width: 140,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Width',
                    dataIndx: 'width',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Height',
                    dataIndx: 'height',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Rim Size',
                    dataIndx: 'rim_size',
                    width: 110,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Load',
                    dataIndx: 'load_index',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Speed',
                    dataIndx: 'speed_rating',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'DOT',
                    dataIndx: 'dot_year',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Type',
                    dataIndx: 'tyre_type',
                    width: 130,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Runflat',
                    dataIndx: 'runflat',
                    width: 100,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Status',
                    dataIndx: 'inventory_status',
                    width: 170,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                    render: function (ui) {
                        return inventoryStatusBadge(ui.cellData);
                    },
                },
                {
                    title: 'Retail Price',
                    dataIndx: 'retail_price',
                    width: 140,
                    editable: false,
                    align: 'right',
                    render: function (ui) {
                        return formatMoney(ui.cellData);
                    },
                },
                {
                    title: 'Wholesale Price',
                    dataIndx: 'wholesale_price_lvl1',
                    width: 150,
                    editable: false,
                    align: 'right',
                    render: function (ui) {
                        return formatMoney(ui.cellData);
                    },
                },
            ];
        }

        function warehouseColumns() {
            const columns = [];

            allWarehouses.forEach(function (warehouse) {
                const qtyField = 'qty' + warehouse.id;
                const etaField = 'eta' + warehouse.id;
                const incomingField = 'e_ta_q_ty' + warehouse.id;

                columns.push({
                    title: warehouse.code,
                    dataIndx: qtyField,
                    width: 120,
                    dataType: 'integer',
                    align: 'center',
                    cls: 'inventory-cell',
                    editable: canEditCells,
                    filter: { crules: [{ condition: 'equal' }] },
                });

                columns.push({
                    title: 'ETA ' + warehouse.code,
                    dataIndx: etaField,
                    width: 150,
                    dataType: 'string',
                    align: 'center',
                    cls: 'inventory-cell-eta',
                    editable: canEditCells,
                    filter: { crules: [{ condition: 'begin' }] },
                });

                columns.push({
                    title: 'Incoming ' + warehouse.code,
                    dataIndx: incomingField,
                    width: 150,
                    dataType: 'integer',
                    align: 'center',
                    cls: 'inventory-cell-incoming',
                    editable: canEditCells,
                    filter: { crules: [{ condition: 'equal' }] },
                    render: function (ui) {
                        const value = parseInt(ui.cellData || 0, 10);
                        if (value > 0) {
                            return '<a href="#" class="incoming-link text-success fw-bold text-decoration-none" data-sku="' + escaped(ui.rowData.sku) + '" data-warehouse="' + escaped(warehouse.code) + '">' + value + '</a>';
                        }

                        return value || 0;
                    },
                });
            });

            return columns;
        }

        function summaryColumns() {
            return [
                {
                    title: 'Current Stock',
                    dataIndx: 'current_stock',
                    width: 130,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Incoming Total',
                    dataIndx: 'incoming_stock',
                    width: 140,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                },
                {
                    title: 'Consignment',
                    dataIndx: 'consignment_stock',
                    width: 140,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                    render: function (ui) {
                        const value = parseInt(ui.cellData || 0, 10);
                        if (value > 0) {
                            return '<a href="#" class="consignment-link text-primary fw-bold text-decoration-none" data-sku="' + escaped(ui.rowData.sku) + '">' + value + '</a>';
                        }

                        return value || 0;
                    },
                },
                {
                    title: 'Damaged',
                    dataIndx: 'damaged_stock',
                    width: 120,
                    editable: false,
                    align: 'center',
                    filter: { crules: [{ condition: 'equal' }] },
                    render: function (ui) {
                        const value = parseInt(ui.cellData || 0, 10);
                        if (value > 0) {
                            return '<a href="#" class="damaged-link text-danger fw-bold text-decoration-none" data-sku="' + escaped(ui.rowData.sku) + '">' + value + '</a>';
                        }

                        return value || 0;
                    },
                },
            ];
        }

        function buildToolbar() {
            return {
                cls: 'pq-toolbar-export',
                items: [
                    {
                        type: 'select',
                        label: 'Format: ',
                        attr: 'id="tyre_export_format"',
                        options: [{ xlsx: 'Excel', csv: 'Csv', htm: 'Html' }],
                    },
                    {
                        type: 'button',
                        label: ' Export',
                        cls: 'btn btn-primary',
                        listener: function () {
                            let format = $('#tyre_export_format').val();
                            let blob = this.exportData({
                                format: format,
                                nopqdata: true,
                                render: true,
                            });

                            if (typeof blob === 'string') {
                                blob = new Blob([blob]);
                            }

                            saveAs(blob, 'TyreInventory.' + format);
                        },
                    },
                    { type: 'separator' },
                    {
                        type: 'textbox',
                        label: 'Search: ',
                        attr: 'placeholder="Enter text"',
                        listener: {
                            timeout: function (evt) {
                                const txt = $(evt.target).val();
                                const rules = this.getCMPrimary().map(function (column) {
                                    return {
                                        dataIndx: column.dataIndx,
                                        condition: 'contain',
                                        value: txt,
                                    };
                                });

                                this.filter({
                                    mode: 'OR',
                                    rules: rules,
                                });
                            },
                        },
                    },
                ],
            };
        }

        function initializeGrid(rows) {
            const data = hydrateTyreRows(rows);
            const colModel = baseColumns().concat(warehouseColumns(), summaryColumns());

            if (grid) {
                $('#grid_json_inventory').pqGrid('destroy');
                grid = null;
            }

            const obj = {
                width: '100%',
                height: 700,
                title: 'Tyre Inventory Grid - ' + allWarehouses.length + ' Warehouses',
                scrollModel: { horizontal: true, autoFit: false },
                numberCell: { show: true, title: '#' },
                colModel: colModel,
                dataModel: {
                    dataType: 'JSON',
                    recIndx: 'id',
                    data: data,
                },
                toolbar: buildToolbar(),
                filterModel: { on: true, mode: 'AND', header: true },
                editor: { select: true },
                editModel: {
                    saveKey: $.ui.keyCode.ENTER,
                    keyUpDown: false,
                    cellBorderWidth: 0,
                },
                pageModel: {
                    type: 'local',
                    rPP: 50,
                    rPPOptions: [20, 50, 100, 250, 500, 1000],
                    curPage: 1,
                    strRpp: 'Rows per page: {0}',
                    strDisplay: 'Showing {0} - {1} of {2}',
                    strPage: 'Page {0} of {1}',
                },
                resizable: true,
                rowBorders: true,
                columnBorders: true,
                freezeCols: 5,
                wrap: false,
                hwrap: false,
                trackModel: { on: true },
                historyModel: { on: true },
                track: true,
                selectionModel: { type: 'cell', mode: 'block' },
                copyModel: { on: true },
                change: function (evt, ui) {
                    if (window._tyreCheckboxToggling || ui.source === 'checkbox') {
                        return;
                    }

                    const updates = ui.updateList || [];
                    if (updates.length === 1 && Object.keys(updates[0].newRow || {}).join('') === 'state') {
                        return;
                    }

                    clearTimeout(window.tyreGridSaveTimeout);
                    window.tyreGridSaveTimeout = setTimeout(function () {
                        saveChanges();
                    }, 300);
                },
            };

            $('#grid_json_inventory').pqGrid(obj);
            grid = $('#grid_json_inventory').pqGrid('instance');
        }

        function loadGrid() {
            $.ajax({
                url: gridEndpoint,
                method: 'GET',
                dataType: 'json',
                beforeSend: function () {
                    $('#grid_json_inventory').html('<div class="text-center py-5 text-muted">Loading tyre inventory grid...</div>');
                },
                success: function (response) {
                    initializeGrid(response || []);
                },
                error: function () {
                    $('#grid_json_inventory').html('<div class="alert alert-danger mb-0">Failed to load tyre inventory grid.</div>');
                },
            });
        }

        $(document).ready(function () {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            loadGrid();

            $('#save-changes-btn').on('click', function () {
                saveChanges();
            });

            $('#import-inv-btn').on('click', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('import-tyre-inventory-modal')).show();
            });

            $('#inventoryImportForm').on('submit', function () {
                $('#processingOverlay').css('display', 'flex');
                $('#importInventoryBtn').prop('disabled', true).text('Importing...');
            });

            $(document).on('click', '.consignment-link', function (event) {
                event.preventDefault();
                loadConsignmentModal($(this).data('sku'));
            });

            $(document).on('click', '.incoming-link', function (event) {
                event.preventDefault();
                loadIncomingModal($(this).data('sku'), $(this).data('warehouse'));
            });

            $(document).on('click', '.damaged-link', function (event) {
                event.preventDefault();
                loadDamagedModal($(this).data('sku'));
            });

            $('#bulk-transfer-btn').on('click', function () {
                const rows = selectedRows();
                const html = rows.length
                    ? rows.map(function (row, index) { return buildTransferRow(row, index); }).join('')
                    : buildTransferRow();

                $('#bulk-transfer-rows').html(html);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('bulk-transfer-modal')).show();
            });

            $('#bt-add-line-btn').on('click', function () {
                $('#bulk-transfer-rows').append(buildTransferRow(null, $('#bulk-transfer-rows tr').length));
            });

            $(document).on('click', '.bt-remove', function () {
                $(this).closest('tr').remove();
            });

            $(document).on('click', '#bulk-transfer-rows .qty-dec', function () {
                const $input = $(this).siblings('.bt-qty');
                $input.val(Math.max(0, parseInt($input.val() || 0, 10) - 1));
            });

            $(document).on('click', '#bulk-transfer-rows .qty-inc', function () {
                const $input = $(this).siblings('.bt-qty');
                $input.val(parseInt($input.val() || 0, 10) + 1);
            });

            $(document).on('change', '#bulk-transfer-rows .bt-sku', function () {
                const $row = $(this).closest('tr');
                const found = findTyreRowBySku($(this).val());
                $row.attr('data-offer-id', found ? found.id : '');
                const from = $row.find('.bt-from').val();
                $row.find('.bt-available').text(found && from ? availabilityForOffer(found.id, from) : 0);
            });

            $(document).on('change', '#bulk-transfer-rows .bt-from', function () {
                const $row = $(this).closest('tr');
                const offerId = $row.data('offer-id');
                $row.find('.bt-available').text(availabilityForOffer(offerId, $(this).val()));
            });

            $('#bt-transfer-btn').on('click', function () {
                const lines = [];
                let valid = true;

                $('#bulk-transfer-rows tr').each(function () {
                    const $row = $(this);
                    const offerId = $row.data('offer-id');
                    const from = $row.find('.bt-from').val();
                    const to = $row.find('.bt-to').val();
                    const quantity = parseInt($row.find('.bt-qty').val() || 0, 10);

                    if (!offerId || !from || !to || quantity <= 0 || String(from) === String(to)) {
                        valid = false;
                        return false;
                    }

                    lines.push({ offer_id: offerId, from: from, to: to, quantity: quantity });
                });

                if (!valid || !lines.length) {
                    showToast('Please complete all transfer rows with valid values.', 'error');
                    return;
                }

                $('#bt-transfer-btn').prop('disabled', true).text('Transferring...');

                $.ajax({
                    url: bulkTransferEndpoint,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ lines: lines }),
                    success: function (response) {
                        showToast(response.message || 'Tyre stock transferred successfully.');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('bulk-transfer-modal')).hide();
                        loadGrid();
                    },
                    error: function (xhr) {
                        showToast(xhr.responseJSON?.message || 'Failed to transfer tyre stock.', 'error');
                    },
                    complete: function () {
                        $('#bt-transfer-btn').prop('disabled', false).text('Transfer Stock');
                    },
                });
            });

            $('#add-inventory-btn').on('click', function () {
                const rows = selectedRows();
                const html = rows.length
                    ? rows.map(function (row, index) { return buildAddRow(row, index); }).join('')
                    : buildAddRow();

                $('#add-inventory-rows').html(html);
                $('#ai-reference').val('');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('add-inventory-modal')).show();
            });

            $('#ai-add-line-btn').on('click', function () {
                $('#add-inventory-rows').append(buildAddRow(null, $('#add-inventory-rows tr').length));
            });

            $(document).on('click', '.ai-remove', function () {
                $(this).closest('tr').remove();
            });

            $(document).on('click', '#add-inventory-rows .qty-dec', function () {
                const $input = $(this).siblings('.ai-qty');
                $input.val(Math.max(0, parseInt($input.val() || 0, 10) - 1));
            });

            $(document).on('click', '#add-inventory-rows .qty-inc', function () {
                const $input = $(this).siblings('.ai-qty');
                $input.val(parseInt($input.val() || 0, 10) + 1);
            });

            $(document).on('change', '#add-inventory-rows .ai-sku', function () {
                const found = findTyreRowBySku($(this).val());
                $(this).closest('tr').attr('data-offer-id', found ? found.id : '');
            });

            $('#ai-submit-btn').on('click', function () {
                const lines = [];
                let valid = true;

                $('#add-inventory-rows tr').each(function () {
                    const $row = $(this);
                    const offerId = $row.data('offer-id');
                    const to = $row.find('.ai-to').val();
                    const quantity = parseInt($row.find('.ai-qty').val() || 0, 10);

                    if (!offerId || !to || quantity <= 0) {
                        valid = false;
                        return false;
                    }

                    lines.push({ offer_id: offerId, to: to, quantity: quantity });
                });

                if (!valid || !lines.length) {
                    showToast('Please complete all inventory lines with valid values.', 'error');
                    return;
                }

                $('#ai-submit-btn').prop('disabled', true).text('Adding...');

                $.ajax({
                    url: addInventoryEndpoint,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        lines: lines,
                        reference: $('#ai-reference').val().trim(),
                    }),
                    success: function (response) {
                        showToast(response.message || 'Tyre inventory added successfully.');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('add-inventory-modal')).hide();
                        loadGrid();
                    },
                    error: function (xhr) {
                        showToast(xhr.responseJSON?.message || 'Failed to add tyre inventory.', 'error');
                    },
                    complete: function () {
                        $('#ai-submit-btn').prop('disabled', false).text('Add Inventory');
                    },
                });
            });
        });
    </script>
</div>
@endpush
