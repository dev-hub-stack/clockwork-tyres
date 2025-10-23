@extends('layouts.app')

@section('title', 'Edit Addon')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1>Edit Addon</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('addons.index', ['category' => $addon->category->slug]) }}">Add Ons</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Error Messages -->
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>Validation Errors:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Edit Form -->
    <form action="{{ route('addons.update', $addon) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Addon Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Category -->
                    <div class="col-md-6 mb-3">
                        <label for="addon_category_id" class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="addon_category_id" 
                                id="addon_category_id" 
                                class="form-select @error('addon_category_id') is-invalid @enderror"
                                onchange="handleCategoryChange()">
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('addon_category_id', $addon->addon_category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('addon_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div class="col-md-6 mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="title" 
                               id="title" 
                               class="form-control @error('title') is-invalid @enderror" 
                               value="{{ old('title', $addon->title) }}"
                               required
                               maxlength="180">
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Part Number -->
                    <div class="col-md-6 mb-3">
                        <label for="part_number" class="form-label">Part Number</label>
                        <input type="text" 
                               name="part_number" 
                               id="part_number" 
                               class="form-control @error('part_number') is-invalid @enderror" 
                               value="{{ old('part_number', $addon->part_number) }}">
                        @error('part_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="col-12 mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" 
                                  id="description" 
                                  class="form-control @error('description') is-invalid @enderror" 
                                  rows="3">{{ old('description', $addon->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Price -->
                    <div class="col-md-4 mb-3">
                        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   name="price" 
                                   id="price" 
                                   class="form-control @error('price') is-invalid @enderror" 
                                   value="{{ old('price', $addon->price) }}"
                                   step="0.01"
                                   min="0"
                                   required>
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Wholesale Price -->
                    <div class="col-md-4 mb-3">
                        <label for="wholesale_price" class="form-label">Wholesale Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   name="wholesale_price" 
                                   id="wholesale_price" 
                                   class="form-control @error('wholesale_price') is-invalid @enderror" 
                                   value="{{ old('wholesale_price', $addon->wholesale_price) }}"
                                   step="0.01"
                                   min="0">
                            @error('wholesale_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Tax Inclusive -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label d-block">Tax Inclusive</label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="tax_inclusive" value="0">
                            <input type="checkbox" 
                                   name="tax_inclusive" 
                                   id="tax_inclusive" 
                                   class="form-check-input" 
                                   value="1"
                                   {{ old('tax_inclusive', $addon->tax_inclusive) ? 'checked' : '' }}>
                            <label class="form-check-label" for="tax_inclusive">
                                Yes
                            </label>
                        </div>
                    </div>

                    <!-- Stock Status -->
                    <div class="col-md-6 mb-3">
                        <label for="stock_status" class="form-label">Stock Status <span class="text-danger">*</span></label>
                        <select name="stock_status" id="stock_status" class="form-select @error('stock_status') is-invalid @enderror" required>
                            <option value="1" {{ old('stock_status', $addon->stock_status) == 1 ? 'selected' : '' }}>In Stock</option>
                            <option value="0" {{ old('stock_status', $addon->stock_status) == 0 ? 'selected' : '' }}>Out of Stock</option>
                            <option value="2" {{ old('stock_status', $addon->stock_status) == 2 ? 'selected' : '' }}>Backorder</option>
                        </select>
                        @error('stock_status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Total Quantity -->
                    <div class="col-md-6 mb-3">
                        <label for="total_quantity" class="form-label">Total Quantity <span class="text-danger">*</span></label>
                        <input type="number" 
                               name="total_quantity" 
                               id="total_quantity" 
                               class="form-control @error('total_quantity') is-invalid @enderror" 
                               value="{{ old('total_quantity', $addon->total_quantity) }}"
                               min="0"
                               required>
                        @error('total_quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Category-Specific Fields -->
                    <div id="categorySpecificFields">
                        @include('addons.partials.category-fields', ['category' => $selectedCategory, 'addon' => $addon])
                    </div>

                    <!-- Image 1 -->
                    <div class="col-md-6 mb-3">
                        <label for="image_1" class="form-label">Image 1</label>
                        @if($addon->image_1_url)
                            <div class="mb-2">
                                <img src="{{ $addon->image_1_url }}" alt="Current Image 1" class="img-thumbnail" style="max-width: 150px;">
                            </div>
                        @endif
                        <input type="file" 
                               name="image_1" 
                               id="image_1" 
                               class="form-control @error('image_1') is-invalid @enderror"
                               accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                        @error('image_1')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Image 2 -->
                    <div class="col-md-6 mb-3">
                        <label for="image_2" class="form-label">Image 2</label>
                        @if($addon->image_2_url)
                            <div class="mb-2">
                                <img src="{{ $addon->image_2_url }}" alt="Current Image 2" class="img-thumbnail" style="max-width: 150px;">
                            </div>
                        @endif
                        <input type="file" 
                               name="image_2" 
                               id="image_2" 
                               class="form-control @error('image_2') is-invalid @enderror"
                               accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                        @error('image_2')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Update Addon</button>
                <a href="{{ route('addons.index', ['category' => $addon->category->slug]) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function handleCategoryChange() {
    const categoryId = document.getElementById('addon_category_id').value;
    
    // Fetch category-specific fields via AJAX
    fetch(`/api/addon-categories/${categoryId}/fields`)
        .then(response => response.json())
        .then(data => {
            // Update the category-specific fields section
            // This would require creating a partial view and loading it dynamically
            console.log('Category fields:', data);
        });
}
</script>
@endpush
@endsection
