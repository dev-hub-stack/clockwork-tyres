<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Products Grid - Reporting CRM</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- jQuery UI CSS (required for pqGrid) -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- pqGrid CSS - LOCAL -->
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .grid-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .toolbar {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .toolbar .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        #productsGrid {
            margin-top: 10px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">
            <i class="bi bi-grid-3x3-gap"></i> Products Grid
        </h1>

        <!-- Grid Container -->
        <div class="grid-container">
            <!-- Toolbar -->
            <div class="toolbar">
                <button id="btnAddRow" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle"></i> Add Product
                </button>
                <button id="btnDeleteSelected" class="btn btn-danger btn-sm">
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
                <button id="btnSaveAll" class="btn btn-primary btn-sm">
                    <i class="bi bi-save"></i> Save All Changes
                </button>
                <button id="btnRefresh" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button id="btnExportExcel" class="btn btn-info btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export to Excel
                </button>
            </div>

            <!-- Grid -->
            <div id="productsGrid"></div>
        </div>
    </div>

    <!-- jQuery (required for pqGrid) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- jQuery UI (required for pqGrid) -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- pqGrid JS - LOCAL -->
    <script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>
    
    <!-- Products Grid JavaScript -->
    <script>
        // Embed data directly in page (Tunerstop style)
        var data = @json($products_data);
        console.log('Loaded ' + data.length + ' product variants');
    </script>
    <script src="{{ asset('js/products-grid.js') }}"></script>
</body>
</html>