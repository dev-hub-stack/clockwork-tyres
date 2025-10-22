<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Product Images - {{ $image->brand->name ?? '' }} {{ $image->model->name ?? '' }} {{ $image->finish->name ?? '' }}</title>
    
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
            padding: 30px;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .image-upload-section {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
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
                    <a href="{{ route('admin.products.images.index') }}" class="text-decoration-none text-dark">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    Edit: {{ $image->brand->name ?? 'N/A' }} {{ $image->model->name ?? 'N/A' }} {{ $image->finish->name ?? 'N/A' }}
                </h1>
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
            <form action="{{ route('admin.products.images.update', $image->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <select name="brand_id" class="form-select" required>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" {{ $image->brand_id == $brand->id ? 'selected' : '' }}>
                                    {{ $brand->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Model</label>
                        <select name="model_id" class="form-select" required>
                            @foreach($models as $model)
                                <option value="{{ $model->id }}" {{ $image->model_id == $model->id ? 'selected' : '' }}>
                                    {{ $model->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Finish</label>
                        <select name="finish_id" class="form-select" required>
                            @foreach($finishes as $finish)
                                <option value="{{ $finish->id }}" {{ $image->finish_id == $finish->id ? 'selected' : '' }}>
                                    {{ $finish->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3"><i class="bi bi-images"></i> Product Images</h5>
                <p class="text-muted mb-4">Manage up to 9 images for this product combination. Images are stored in CloudFront CDN.</p>

                @for($i = 1; $i <= 9; $i++)
                    @php
                        $field = "image_{$i}";
                        $currentImage = $image->{$field};
                    @endphp
                    
                    <div class="image-upload-section">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Image {{ $i }}</label>
                            </div>
                            <div class="col-md-3">
                                @if($currentImage)
                                    <img src="{{ \App\Utility\Helper::getImageThumbnailPath($currentImage) }}" 
                                         class="image-preview" 
                                         alt="Image {{ $i }}">
                                @else
                                    <div class="image-preview d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-image text-muted" style="font-size: 48px;"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-5">
                                @if($currentImage)
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Current: {{ $currentImage }}</small>
                                    </div>
                                @endif
                                <input type="file" 
                                       name="{{ $field }}" 
                                       class="form-control" 
                                       accept="image/*">
                                <small class="form-text text-muted">Upload new image to replace (optional)</small>
                            </div>
                            <div class="col-md-2">
                                @if($currentImage)
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               name="remove_{{ $field }}" 
                                               class="form-check-input" 
                                               id="remove_{{ $field }}">
                                        <label class="form-check-label text-danger" for="remove_{{ $field }}">
                                            <i class="bi bi-trash"></i> Remove
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endfor

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="{{ route('admin.products.images.index') }}" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
