<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Product Images - Reporting CRM</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        .content-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 20px;
        }
        .filter-input {
            font-size: 13px;
            padding: 4px 8px;
        }
        .action-buttons .btn {
            padding: 4px 12px;
            font-size: 13px;
        }
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #dee2e6;
        }
        .table tbody td {
            vertical-align: middle;
            font-size: 14px;
        }
        .sort-icon {
            cursor: pointer;
            color: #6c757d;
        }
        .sort-icon:hover {
            color: #007bff;
        }
        .images-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-title">
                    <i class="bi bi-images"></i> Product Images
                </h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-cloud-upload"></i> Import CSV
                    </button>
                    <a href="{{ route('admin.products.images.export', request()->all()) }}" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export
                    </a>
                    <a href="/admin/products-grid" class="btn btn-secondary">
                        <i class="bi bi-grid"></i> Products Grid
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 150px;">
                                Brand
                                <a href="{{ route('admin.products.images.index', array_merge(request()->all(), ['order_by' => 'brand', 'sort_order' => $nextSortOrder])) }}">
                                    <i class="bi bi-arrow-down-up sort-icon"></i>
                                </a>
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('brand') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="brand" value="{{ request('brand') }}" 
                                           class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 150px;">
                                Model
                                <a href="{{ route('admin.products.images.index', array_merge(request()->all(), ['order_by' => 'model', 'sort_order' => $nextSortOrder])) }}">
                                    <i class="bi bi-arrow-down-up sort-icon"></i>
                                </a>
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('model') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="model" value="{{ request('model') }}" 
                                           class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 150px;">
                                Finish
                                <a href="{{ route('admin.products.images.index', array_merge(request()->all(), ['order_by' => 'finish', 'sort_order' => $nextSortOrder])) }}">
                                    <i class="bi bi-arrow-down-up sort-icon"></i>
                                </a>
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('finish') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="finish" value="{{ request('finish') }}" 
                                           class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th>Images</th>
                            <th style="width: 100px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($images as $image)
                            <tr>
                                <td>{{ $image->brand->name ?? 'N/A' }}</td>
                                <td>{{ $image->model->name ?? 'N/A' }}</td>
                                <td>{{ $image->finish->finish ?? 'N/A' }}</td>
                                <td>
                                    <div class="images-cell">
                                        @for($i = 1; $i <= 9; $i++)
                                            @php $imageField = "image_{$i}"; @endphp
                                            @if($image->{$imageField})
                                                <img src="{{ \App\Utility\Helper::getImageThumbnailPath($image->{$imageField}) }}" 
                                                     style="width:100px; height:100px; object-fit:cover; border-radius:4px; border:1px solid #dee2e6;"
                                                     alt="Product Image {{ $i }}">
                                            @endif
                                        @endfor
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <a href="{{ route('admin.products.images.edit', $image->id) }}" 
                                           class="btn btn-sm btn-primary" 
                                           title="Edit">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                    <p class="mt-2">No product images found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    Showing {{ $images->firstItem() ?? 0 }} to {{ $images->lastItem() ?? 0 }} of {{ $images->total() }} entries
                </div>
                <div>
                    {{ $images->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Import Product Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.products.images.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="importFile" class="form-label">Select CSV File</label>
                            <input type="file" name="importFile" id="importFile" 
                                   class="form-control" 
                                   accept=".csv,.txt" 
                                   required>
                            <div class="form-text">
                                CSV should contain: Brand, Model, Finish, Image1, Image2, ..., Image9
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> The CSV file should contain brand name, model name, finish name, 
                            and up to 9 image filenames. Existing records will be updated.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Auto-submit filter forms on Enter key
        document.querySelectorAll('.filter-input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });
        });
    </script>
</body>
</html>
