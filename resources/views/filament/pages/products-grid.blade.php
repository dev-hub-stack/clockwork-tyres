<x-filament-panels::page>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- pqGrid CSS - LOCAL -->
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .page-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
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
    </style>
    
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
                <form action="{{ route('admin.products.bulk.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
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
                        <button type="submit" class="btn btn-success">
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
    
    <!-- pqGrid JS - LOCAL -->
    <script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>
    
    <!-- FileSaver.js for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <!-- Embed data (Tunerstop style) -->
    <script>
        var data = @json($products_data);
        console.log('✅ Loaded ' + data.length + ' product variants');
    </script>
    
    <!-- Products Grid JavaScript -->
    <script src="{{ asset('js/products-grid.js') }}"></script>
</x-filament-panels::page>
