<x-filament-panels::page>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- pqGrid PRO CSS - LOCAL (Required for filter headers!) -->
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* GRID CONTAINER - Full width with horizontal scroll */
        .page-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 100%;
            max-width: 100%;
            overflow-x: auto; /* Enable horizontal scroll if needed */
        }
        
        #productsGrid {
            min-height: 600px;
            width: 100% !important;
            max-width: none !important;
        }
        
        /* Ensure grid doesn't get squeezed */
        .pq-grid {
            width: 100% !important;
            min-width: 100%;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        #productsGrid {
            min-height: 600px;
        }
        
        /* Delete button styling */
        .delete_btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .delete_btn:hover {
            background: #c82333;
        }
        
        /* pqGrid checkbox styling */
        .pq-grid-row .ui-state-default input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }
        
        /* FILL HANDLE - Excel-like drag down indicator */
        .pq-grid-fill-handle {
            background: #2563eb !important;  /* Blue color */
            border: 2px solid #1e40af !important;
            width: 8px !important;
            height: 8px !important;
            cursor: crosshair !important;
            z-index: 1000 !important;
        }
        
        /* Cell selection border */
        .pq-grid-cell.pq-state-select {
            border: 2px solid #2563eb !important;
            background: #dbeafe !important;
        }
        
        /* GRID LINES - Make them visible like Tunerstop */
        .pq-grid-cell,
        .pq-grid-col {
            border: 1px solid #d1d5db !important;  /* Visible gray borders */
            border-right-color: #d1d5db !important;
            border-bottom-color: #d1d5db !important;
        }
        
        .pq-grid-row {
            border-bottom: 1px solid #d1d5db !important;
        }
        
        /* Striped rows for better readability */
        .pq-grid-row.pq-striped {
            background: #f9fafb !important;
        }
        
        /* Header cells */
        .pq-grid-header-table .pq-grid-col {
            border: 1px solid #9ca3af !important;
            background: #f3f4f6 !important;
            font-weight: 600 !important;
        }
        
        /* Hide sort arrows in filter row cells */
        .pq-grid-header-search-row .pq-sort-icon,
        .pq-grid-header-search-row .ui-icon {
            display: none !important;
        }
        
        /* CRITICAL: Force filter header row to show - Updated */
        .pq-grid-header-search-row,
        tr.pq-grid-header-search-row {
            display: table-row !important;
            visibility: visible !important;
            height: auto !important;
            background-color: #f8f9fa !important;
        }
        
        /* Ensure header table shows filter row */
        .pq-grid-header-table .pq-grid-header-search-row {
            display: table-row !important;
        }
        
        /* Filter input fields - Enhanced styling */
        .pq-grid-hd-search-field,
        input.pq-grid-hd-search-field {
            display: block !important;
            width: 95% !important;
            padding: 6px 10px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            background: #ffffff !important;
            color: #111827 !important;
            box-sizing: border-box !important;
        }
        
        .pq-grid-hd-search-field:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1) !important;
        }
        
        /* Filter row cells */
        .pq-grid-header-search-row .pq-grid-col,
        .pq-grid-header-search-row td {
            padding: 5px !important;
            background: #f8f9fa !important;
            border-color: #e5e7eb !important;
            vertical-align: middle !important;
        }
        
        /* Make sure the filter row is after header row */
        .pq-grid-header-table tbody tr:nth-child(2) {
            display: table-row !important;
        }
        
        /* FULL WIDTH FIX - Override Filament constraints */
        .fi-main,
        .fi-page,
        .fi-page-content {
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>
    
    <!-- Performance Notice -->
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <strong><i class="bi bi-exclamation-triangle"></i> Performance Notice:</strong> 
        For optimal performance, this page now loads <strong>up to 1000 product variants</strong> at a time.
        If you need to view all products, use the <strong>search and filter</strong> functionality in the grid.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    
    <!-- Sidebar Collapse Help Alert -->
    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert" style="background: #e0f2fe; border-color: #0ea5e9;">
        <strong><i class="bi bi-info-circle"></i> Tip:</strong> 
        To see wider columns, <strong>collapse the sidebar</strong> by clicking the <strong>"☰" (hamburger menu)</strong> icon at the <strong>top-left corner</strong> of the page.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    
    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    
    @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <strong><i class="bi bi-exclamation-circle"></i> Import Errors ({{ count(session('import_errors')) }}):</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <ul class="mb-0 mt-2" style="max-height: 200px; overflow-y: auto;">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkProductModal">
            <i class="bi bi-upload"></i> Bulk Upload Products
        </button>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkImagesModal">
            <i class="bi bi-images"></i> Bulk Upload Images
        </button>
        <button class="btn btn-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
    
    <!-- Grid Container -->
    <div class="page-content">
        <div id="productsGrid"></div>
    </div>
    
    <!-- Bulk Product Upload Modal -->
    <div class="modal fade" id="bulkProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-upload"></i> Bulk Upload Products</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.products.bulk.import') }}" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                    @csrf
                    <div class="modal-body">
                        <!-- Loading Overlay -->
                        <div id="uploadLoader" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); z-index: 9999; border-radius: 8px;">
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <h4 class="mt-3 text-success">Processing Import...</h4>
                                <p class="text-muted">Please wait while we process your file.<br>This may take a few minutes for large files.</p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Upload Excel/CSV File</label>
                                    <input type="file" name="importFile" class="form-control" 
                                           accept=".csv,.xlsx,.xls" required>
                                    <div class="form-text">Supported: CSV, XLSX, XLS</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Sample File</label><br>
                                    <a href="{{ asset('uploads/samplefiles/products-sample.csv') }}" 
                                       class="btn btn-warning" download>
                                        <i class="bi bi-download"></i> Download Sample
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle"></i> Instructions:</strong>
                            <ul class="mb-0">
                                <li>Download the sample file to see the required format</li>
                                <li>Fill in all required columns: <strong>SKU is mandatory</strong></li>
                                <li>Do not modify column headers</li>
                                <li>Save as .xlsx or .csv format</li>
                                <li><strong>✅ Supports up to 5000+ products per file!</strong></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-success">
                            <strong><i class="bi bi-lightning-charge"></i> Performance:</strong>
                            <ul class="mb-0">
                                <li><strong>100 products:</strong> ~10 seconds</li>
                                <li><strong>500 products:</strong> ~30 seconds</li>
                                <li><strong>1000 products:</strong> ~1 minute</li>
                                <li><strong>5000 products:</strong> ~4-5 minutes</li>
                            </ul>
                            <small class="text-muted">Processing time may vary based on server performance</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="uploadBtn">
                            <i class="bi bi-upload"></i> Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Images Upload Modal -->
    <div class="modal fade" id="bulkImagesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-images"></i> Bulk Upload Product Images</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.products.bulk.images') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Upload Images ZIP File</label>
                            <input type="file" name="imagesZip" class="form-control" 
                                   accept=".zip" required>
                            <div class="form-text">Upload a ZIP file containing product images</div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong><i class="bi bi-exclamation-triangle"></i> Important:</strong>
                            <ul class="mb-0">
                                <li>Image filenames MUST match product SKUs (e.g., <code>TWA-12345.jpg</code>)</li>
                                <li>Supported formats: JPG, PNG, WEBP</li>
                                <li>Recommended size: 800x800px or larger</li>
                                <li>Max file size per image: 2MB</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Example ZIP structure:</strong>
                            <pre class="mb-0">images.zip
├── TWA-20x9-001.jpg
├── TWA-20x10-002.jpg
├── TWB-22x9-003.png
└── ...</pre>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-upload"></i> Upload Images
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- jQuery UI -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- pqGrid PRO JS - LOCAL (Required for filter headers!) -->
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
    
    <!-- FileSaver.js for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <!-- Bulk Upload Loader Script -->
    <script>
        $(document).ready(function() {
            // Show loader when bulk upload form is submitted
            $('#bulkUploadForm').on('submit', function(e) {
                // Show loader
                $('#uploadLoader').fadeIn();
                $('#uploadBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Processing...');
                
                // Note: Form will submit normally, loader will show until page reloads
            });
        });
    </script>
    
    <!-- Embed data (Tunerstop style) -->
    <script>
        var data = @json($products_data);
        console.log('✅ Loaded ' + data.length + ' product variants');
    </script>
    
    <!-- Products Grid JavaScript -->
    <script src="{{ asset('js/products-grid.js') }}"></script>
</x-filament-panels::page>
