@extends('layouts.app')

@section('title', 'Add Ons')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content" style="width: 50px; height: 50px; margin-right: 15px;">
                    <i class="fas fa-plus text-white" style="font-size: 24px; margin-left: 13px;"></i>
                </div>
                <h1 class="mb-0">Add Ons</h1>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills" role="tablist">
                @foreach($categories as $category)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $selectedCategory->id === $category->id ? 'active' : '' }}" 
                           href="{{ route('addons.index', ['category' => $category->slug]) }}"
                           style="{{ $selectedCategory->id === $category->id ? 'background-color: #9ca3af; color: white;' : 'background-color: #d1d5db; color: #374151;' }}">
                            {{ $category->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Search and Action Buttons -->
    <div class="row mb-3">
        <div class="col-md-4">
            <form action="{{ route('addons.index') }}" method="GET" class="d-flex">
                <input type="hidden" name="category" value="{{ $selectedCategory->slug }}">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search" 
                       value="{{ request('search') }}">
                <button type="submit" class="btn btn-light ms-2">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
        <div class="col-md-8 text-end">
            <a href="{{ route('addons.export', ['category' => $selectedCategory->slug]) }}" 
               class="btn btn-outline-secondary">
                <i class="fas fa-download"></i> Export
            </a>
            <a href="{{ route('addons.bulk-upload-images', ['category' => $selectedCategory->slug]) }}" 
               class="btn btn-primary">
                Bulk Upload Images
            </a>
            <a href="{{ route('addons.create', ['category' => $selectedCategory->slug]) }}" 
               class="btn btn-primary">
                Add New
            </a>
            <button type="button" 
                    class="btn btn-danger" 
                    onclick="bulkDelete()"
                    id="bulkDeleteBtn"
                    style="display: none;">
                <i class="fas fa-trash"></i> Bulk Delete
            </button>
        </div>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- AddOns Table -->
    <div class="card">
        <div class="card-body p-0">
            <form id="bulkDeleteForm" action="{{ route('addons.bulk-delete') }}" method="POST">
                @csrf
                @method('DELETE')
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background-color: #2c3e50; color: white;">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th style="width: 80px;">Image</th>
                                <th>Product Details</th>
                                
                                @if($selectedCategory->slug === 'lug-nuts' || $selectedCategory->slug === 'lug-bolts')
                                    <th>Thread Size</th>
                                    <th>Color</th>
                                    <th>Length</th>
                                    <th>Diameter</th>
                                @elseif($selectedCategory->slug === 'hub-rings')
                                    <th>Ext Center Bore</th>
                                    <th>Center Bore</th>
                                @elseif($selectedCategory->slug === 'spacers')
                                    <th>Bolt Pattern</th>
                                    <th>Width</th>
                                    <th>Thread Size</th>
                                    <th>Center Bore</th>
                                @endif
                                
                                <th class="text-center">WH-2<br>California</th>
                                <th class="text-center">WH-1<br>Chicago</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($addons as $addon)
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="addon_ids[]" 
                                               value="{{ $addon->id }}" 
                                               class="addon-checkbox">
                                    </td>
                                    <td>
                                        @if($addon->image_1_url)
                                            <img src="{{ $addon->image_1_url }}" 
                                                 alt="{{ $addon->title }}" 
                                                 class="rounded"
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $addon->title }}</strong>
                                            @if($addon->part_number)
                                                <br><small class="text-muted">{{ $addon->part_number }}</small>
                                            @endif
                                        </div>
                                        @if($addon->description)
                                            <div class="text-muted small mt-1">
                                                Description: {{ Str::limit($addon->description, 100) }}
                                            </div>
                                        @endif
                                    </td>
                                    
                                    @if($selectedCategory->slug === 'lug-nuts')
                                        <td>{{ $addon->thread_size }}</td>
                                        <td>{{ $addon->color }}</td>
                                        <td>{{ $addon->lug_nut_length }}</td>
                                        <td>{{ $addon->lug_nut_diameter }}</td>
                                    @elseif($selectedCategory->slug === 'lug-bolts')
                                        <td>{{ $addon->thread_size }}</td>
                                        <td>{{ $addon->color }}</td>
                                        <td>{{ $addon->thread_length }}</td>
                                        <td>{{ $addon->lug_bolt_diameter }}</td>
                                    @elseif($selectedCategory->slug === 'hub-rings')
                                        <td>{{ $addon->ext_center_bore }}</td>
                                        <td>{{ $addon->center_bore }}</td>
                                    @elseif($selectedCategory->slug === 'spacers')
                                        <td>{{ $addon->bolt_pattern }}</td>
                                        <td>{{ $addon->width }}</td>
                                        <td>{{ $addon->thread_size }}</td>
                                        <td>{{ $addon->center_bore }}</td>
                                    @endif
                                    
                                    <td class="text-center">{{ $addon->total_quantity }}</td>
                                    <td class="text-center">0</td>
                                    <td>
                                        <div class="btn-group-vertical" role="group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-secondary mb-1"
                                                    onclick="event.preventDefault(); if(confirm('Delete this addon?')) document.getElementById('delete-form-{{ $addon->id }}').submit();">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <a href="{{ route('addons.edit', $addon) }}" 
                                               class="btn btn-sm btn-primary mb-1">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="{{ route('addons.show', $addon) }}" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                        
                                        <form id="delete-form-{{ $addon->id }}" 
                                              action="{{ route('addons.destroy', $addon) }}" 
                                              method="POST" 
                                              style="display: none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <p class="text-muted mb-0">No addons found for this category.</p>
                                        <a href="{{ route('addons.create', ['category' => $selectedCategory->slug]) }}" 
                                           class="btn btn-primary mt-2">
                                            Add First Addon
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Pagination -->
    <div class="row mt-3">
        <div class="col-12">
            {{ $addons->appends(request()->query())->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
// Select All Checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.addon-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    toggleBulkDeleteButton();
});

// Individual Checkbox Change
document.querySelectorAll('.addon-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', toggleBulkDeleteButton);
});

// Toggle Bulk Delete Button
function toggleBulkDeleteButton() {
    const checkedBoxes = document.querySelectorAll('.addon-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    
    if (checkedBoxes.length > 0) {
        bulkDeleteBtn.style.display = 'inline-block';
    } else {
        bulkDeleteBtn.style.display = 'none';
    }
}

// Bulk Delete Function
function bulkDelete() {
    const checkedBoxes = document.querySelectorAll('.addon-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one addon to delete.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${checkedBoxes.length} addon(s)?`)) {
        document.getElementById('bulkDeleteForm').submit();
    }
}
</script>
@endpush
@endsection
