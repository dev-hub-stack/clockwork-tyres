<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tyre Images - Reporting CRM</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 20px;
        }
        .filter-input {
            font-size: 13px;
            padding: 4px 8px;
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
        .images-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .image-tile {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background: #fff;
        }
        .account-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef6ff;
            color: #0f4c81;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h1 class="page-title">
                        <a href="/admin" class="btn btn-link text-decoration-none">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <i class="bi bi-images"></i> Tyre Images
                    </h1>
                    <div class="mt-2 account-pill">
                        <i class="bi bi-building"></i>
                        Active account: {{ $account->name }}
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-cloud-upload"></i> Import CSV
                    </button>
                    <a href="{{ route('admin.tyres.images.export', request()->all()) }}" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export
                    </a>
                    <a href="/admin/tyre-grid" class="btn btn-secondary">
                        <i class="bi bi-grid"></i> Tyres Grid
                    </a>
                    <a href="/admin" class="btn btn-outline-secondary">
                        <i class="bi bi-house"></i> Back to Admin
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
            <div class="mb-3 text-muted">
                Manage brand and product images for the current business account. This follows the same separate-image-management pattern as Product Images, but stores media under the dedicated <code>tyres/</code> S3 path.
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 140px;">
                                SKU
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('sku') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="sku" value="{{ request('sku') }}" class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 160px;">
                                Brand
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('brand') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="brand" value="{{ request('brand') }}" class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 180px;">
                                Model
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('model') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="model" value="{{ request('model') }}" class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 140px;">
                                Full Size
                                <form method="get" class="mt-1">
                                    @foreach(request()->except('size') as $key => $value)
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="text" name="size" value="{{ request('size') }}" class="form-control filter-input" placeholder="Filter...">
                                </form>
                            </th>
                            <th style="width: 90px;">Year</th>
                            <th>Images</th>
                            <th style="width: 100px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($images as $offer)
                            @php
                                $tyreGroup = $offer->tyreCatalogGroup;
                                $imageUrls = array_filter([
                                    'Brand' => $offer->brand_image_url,
                                    'Image 1' => $offer->product_image_1_url,
                                    'Image 2' => $offer->product_image_2_url,
                                    'Image 3' => $offer->product_image_3_url,
                                ]);
                            @endphp
                            <tr>
                                <td>{{ $offer->source_sku ?? 'N/A' }}</td>
                                <td>{{ $tyreGroup?->brand_name ?? 'N/A' }}</td>
                                <td>{{ $tyreGroup?->model_name ?? 'N/A' }}</td>
                                <td>{{ $tyreGroup?->full_size ?? 'N/A' }}</td>
                                <td>{{ $tyreGroup?->dot_year ?? 'N/A' }}</td>
                                <td>
                                    <div class="images-cell">
                                        @forelse($imageUrls as $label => $url)
                                            <img src="{{ $url }}" class="image-tile" alt="{{ $label }}">
                                        @empty
                                            <span class="text-muted">No tyre images uploaded</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.tyres.images.edit', $offer->id) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                    <p class="mt-2">No tyre images found for the active account</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                    Showing {{ $images->firstItem() ?? 0 }} to {{ $images->lastItem() ?? 0 }} of {{ $images->total() }} entries
                </div>
                <div>{{ $images->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Import Tyre Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('admin.tyres.images.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="importFile" class="form-label">Select CSV File</label>
                            <input type="file" name="importFile" id="importFile" class="form-control" accept=".csv,.txt" required>
                            <div class="form-text">
                                CSV should contain: SKU, BrandImage, ProductImage1, ProductImage2, ProductImage3
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            The import applies only to the active business account and matches rows by supplier SKU.
                            Image values may be filenames, raw <code>tyres/...</code> paths, or full URLs.
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
