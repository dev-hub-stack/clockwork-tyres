<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Products Grid - Reporting CRM</title>
    
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
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: #1f2937;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #111827;
            border-bottom: 1px solid #374151;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sidebar-header h2 {
            color: #fff;
            font-size: 20px;
            margin: 0;
            transition: all 0.3s;
        }
        
        .sidebar.collapsed .sidebar-header h2 {
            display: none;
        }
        
        .toggle-btn {
            background: #374151;
            border: none;
            color: #fff;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .toggle-btn:hover {
            background: #4b5563;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            color: #9ca3af;
            text-decoration: none;
            padding: 12px 20px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .sidebar-nav a i {
            margin-right: 10px;
            min-width: 20px;
            font-size: 18px;
        }
        
        .sidebar.collapsed .sidebar-nav a span {
            display: none;
        }
        
        .sidebar-nav a:hover {
            background: #374151;
            color: #fff;
        }
        
        .sidebar-nav a.active {
            background: #3b82f6;
            color: #fff;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            background: #f3f4f6;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 60px;
        }
        
        .page-header {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: #1f2937;
        }
        
        .page-header p {
            color: #6b7280;
            margin-bottom: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .grid-container {
            background: #fff;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        #productsGrid {
            min-height: 500px;
        }
        
        /* Delete button styling */
        .delete_btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .delete_btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="bi bi-bar-chart-fill"></i> Reporting CRM</h2>
            <button class="toggle-btn" onclick="toggleSidebar()">
                <i class="bi bi-list" id="toggleIcon"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="/admin"><i class="bi bi-house-door"></i> <span>Dashboard</span></a>
            <a href="/admin/brands"><i class="bi bi-tag"></i> <span>Brands</span></a>
            <a href="/admin/product-models"><i class="bi bi-boxes"></i> <span>Models</span></a>
            <a href="/admin/finishes"><i class="bi bi-palette"></i> <span>Finishes</span></a>
            <a href="/admin/products/grid" class="active"><i class="bi bi-grid-3x3-gap"></i> <span>Products Grid</span></a>
            <a href="/admin/customers"><i class="bi bi-people"></i> <span>Customers</span></a>
            <a href="/admin/settings"><i class="bi bi-gear"></i> <span>Settings</span></a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
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
        
        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-exclamation-circle"></i> Import Errors ({{ count(session('import_errors')) }}):</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <ul class="mb-0 mt-2" style="max-height: 200px; overflow-y: auto;">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-grid-3x3-gap"></i> Products Management Grid</h1>
            <p>Excel-like grid for bulk product editing - Tunerstop Structure</p>
            
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
        </div>
        
        <!-- Grid Container -->
        <div class="grid-container">
            <div id="productsGrid"></div>
        </div>
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
        
        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.className = 'bi bi-list';
            } else {
                toggleIcon.className = 'bi bi-x-lg';
            }
        }
    </script>
    
    <!-- Products Grid JavaScript -->
    <script src="{{ asset('js/products-grid.js') }}"></script>
</body>
</html>
