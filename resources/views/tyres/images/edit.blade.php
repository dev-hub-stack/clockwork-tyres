<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Tyre Images - {{ $offer->source_sku }}</title>

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
            padding: 30px;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
            background: #fff;
        }
        .image-upload-section {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .meta-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            padding: 12px 14px;
            height: 100%;
        }
        .meta-label {
            color: #6c757d;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @php
        $tyreGroup = $offer->tyreCatalogGroup;
        $imageMeta = [
            'brand_image' => ['label' => 'Brand Image', 'url' => $offer->brand_image_url],
            'product_image_1' => ['label' => 'Product Image 1', 'url' => $offer->product_image_1_url],
            'product_image_2' => ['label' => 'Product Image 2', 'url' => $offer->product_image_2_url],
            'product_image_3' => ['label' => 'Product Image 3', 'url' => $offer->product_image_3_url],
        ];
    @endphp

    <div class="page-header">
        <div class="container-fluid">
            <h1 class="page-title">
                <a href="{{ route('admin.tyres.images.index') }}" class="text-decoration-none text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                Edit Tyre Images: {{ $offer->source_sku }}
            </h1>
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
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-label">Business account</div>
                        <div class="mt-2 fw-semibold">{{ $account->name }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-label">Brand / Model</div>
                        <div class="mt-2 fw-semibold">{{ $tyreGroup?->brand_name ?? 'N/A' }}</div>
                        <div>{{ $tyreGroup?->model_name ?? 'N/A' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-label">Size / Year</div>
                        <div class="mt-2 fw-semibold">{{ $tyreGroup?->full_size ?? 'N/A' }}</div>
                        <div>DOT year: {{ $tyreGroup?->dot_year ?? 'N/A' }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="meta-card">
                        <div class="meta-label">Supplier SKU</div>
                        <div class="mt-2 fw-semibold">{{ $offer->source_sku ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.tyres.images.update', $offer->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <h5 class="mb-3"><i class="bi bi-images"></i> Tyre Images</h5>
                <p class="text-muted mb-4">Upload or replace the brand image plus up to three product images. Managed uploads are stored under the dedicated <code>tyres/</code> S3 path.</p>

                @foreach($imageMeta as $field => $meta)
                    @php $currentValue = $offer->getRawOriginal($field); @endphp
                    <div class="image-upload-section">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <label class="form-label fw-bold">{{ $meta['label'] }}</label>
                            </div>
                            <div class="col-md-3">
                                @if($meta['url'])
                                    <img src="{{ $meta['url'] }}" class="image-preview" alt="{{ $meta['label'] }}">
                                @else
                                    <div class="image-preview d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-image text-muted" style="font-size: 48px;"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-5">
                                @if($currentValue)
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Current: {{ $currentValue }}</small>
                                    </div>
                                @endif
                                <input type="file" name="{{ $field }}" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Upload a new image to replace this slot.</small>
                            </div>
                            <div class="col-md-2">
                                @if($currentValue)
                                    <div class="form-check">
                                        <input type="checkbox" name="remove_{{ $field }}" class="form-check-input" id="remove_{{ $field }}" value="1">
                                        <label class="form-check-label text-danger" for="remove_{{ $field }}">
                                            <i class="bi bi-trash"></i> Remove
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="{{ route('admin.tyres.images.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
