{{-- Category-Specific Fields Partial --}}

@if($category->slug === 'lug-nuts')
    <!-- Lug Nuts Fields -->
    <div class="col-md-6 mb-3">
        <label for="thread_size" class="form-label">Thread Size <span class="text-danger">*</span></label>
        <input type="text" 
               name="thread_size" 
               id="thread_size" 
               class="form-control @error('thread_size') is-invalid @enderror" 
               value="{{ old('thread_size', $addon->thread_size ?? '') }}"
               placeholder="e.g., M12x1.5"
               required>
        @error('thread_size')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
        <input type="text" 
               name="color" 
               id="color" 
               class="form-control @error('color') is-invalid @enderror" 
               value="{{ old('color', $addon->color ?? '') }}"
               placeholder="e.g., Chrome, Black"
               required>
        @error('color')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="lug_nut_length" class="form-label">Lug Nut Length <span class="text-danger">*</span></label>
        <input type="text" 
               name="lug_nut_length" 
               id="lug_nut_length" 
               class="form-control @error('lug_nut_length') is-invalid @enderror" 
               value="{{ old('lug_nut_length', $addon->lug_nut_length ?? '') }}"
               placeholder="e.g., 35mm"
               required>
        @error('lug_nut_length')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="lug_nut_diameter" class="form-label">Lug Nut Diameter <span class="text-danger">*</span></label>
        <input type="text" 
               name="lug_nut_diameter" 
               id="lug_nut_diameter" 
               class="form-control @error('lug_nut_diameter') is-invalid @enderror" 
               value="{{ old('lug_nut_diameter', $addon->lug_nut_diameter ?? '') }}"
               placeholder="e.g., 21mm"
               required>
        @error('lug_nut_diameter')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

@elseif($category->slug === 'lug-bolts')
    <!-- Lug Bolts Fields -->
    <div class="col-md-6 mb-3">
        <label for="thread_size" class="form-label">Thread Size <span class="text-danger">*</span></label>
        <input type="text" 
               name="thread_size" 
               id="thread_size" 
               class="form-control @error('thread_size') is-invalid @enderror" 
               value="{{ old('thread_size', $addon->thread_size ?? '') }}"
               placeholder="e.g., M14x1.5"
               required>
        @error('thread_size')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
        <input type="text" 
               name="color" 
               id="color" 
               class="form-control @error('color') is-invalid @enderror" 
               value="{{ old('color', $addon->color ?? '') }}"
               placeholder="e.g., Black, Silver"
               required>
        @error('color')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="thread_length" class="form-label">Thread Length <span class="text-danger">*</span></label>
        <input type="text" 
               name="thread_length" 
               id="thread_length" 
               class="form-control @error('thread_length') is-invalid @enderror" 
               value="{{ old('thread_length', $addon->thread_length ?? '') }}"
               placeholder="e.g., 40mm"
               required>
        @error('thread_length')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="lug_bolt_diameter" class="form-label">Lug Bolt Diameter <span class="text-danger">*</span></label>
        <input type="text" 
               name="lug_bolt_diameter" 
               id="lug_bolt_diameter" 
               class="form-control @error('lug_bolt_diameter') is-invalid @enderror" 
               value="{{ old('lug_bolt_diameter', $addon->lug_bolt_diameter ?? '') }}"
               placeholder="e.g., 17mm"
               required>
        @error('lug_bolt_diameter')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

@elseif($category->slug === 'hub-rings')
    <!-- Hub Rings Fields -->
    <div class="col-md-6 mb-3">
        <label for="ext_center_bore" class="form-label">External Center Bore <span class="text-danger">*</span></label>
        <input type="text" 
               name="ext_center_bore" 
               id="ext_center_bore" 
               class="form-control @error('ext_center_bore') is-invalid @enderror" 
               value="{{ old('ext_center_bore', $addon->ext_center_bore ?? '') }}"
               placeholder="e.g., 73.1mm"
               required>
        @error('ext_center_bore')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="center_bore" class="form-label">Center Bore <span class="text-danger">*</span></label>
        <input type="text" 
               name="center_bore" 
               id="center_bore" 
               class="form-control @error('center_bore') is-invalid @enderror" 
               value="{{ old('center_bore', $addon->center_bore ?? '') }}"
               placeholder="e.g., 56.1mm"
               required>
        @error('center_bore')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

@elseif($category->slug === 'spacers')
    <!-- Spacers Fields -->
    <div class="col-md-6 mb-3">
        <label for="bolt_pattern" class="form-label">Bolt Pattern <span class="text-danger">*</span></label>
        <input type="text" 
               name="bolt_pattern" 
               id="bolt_pattern" 
               class="form-control @error('bolt_pattern') is-invalid @enderror" 
               value="{{ old('bolt_pattern', $addon->bolt_pattern ?? '') }}"
               placeholder="e.g., 5x114.3"
               required>
        @error('bolt_pattern')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="width" class="form-label">Width <span class="text-danger">*</span></label>
        <input type="text" 
               name="width" 
               id="width" 
               class="form-control @error('width') is-invalid @enderror" 
               value="{{ old('width', $addon->width ?? '') }}"
               placeholder="e.g., 20mm"
               required>
        @error('width')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="thread_size" class="form-label">Thread Size <span class="text-danger">*</span></label>
        <input type="text" 
               name="thread_size" 
               id="thread_size" 
               class="form-control @error('thread_size') is-invalid @enderror" 
               value="{{ old('thread_size', $addon->thread_size ?? '') }}"
               placeholder="e.g., M12x1.5"
               required>
        @error('thread_size')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="center_bore" class="form-label">Center Bore <span class="text-danger">*</span></label>
        <input type="text" 
               name="center_bore" 
               id="center_bore" 
               class="form-control @error('center_bore') is-invalid @enderror" 
               value="{{ old('center_bore', $addon->center_bore ?? '') }}"
               placeholder="e.g., 73.1mm"
               required>
        @error('center_bore')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

@elseif($category->slug === 'tpms' || $category->slug === 'wheel-accessories')
    <!-- TPMS and Wheel Accessories - No additional required fields -->
    <div class="col-12 mb-3">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No additional category-specific fields required for {{ $category->name }}.
        </div>
    </div>
@endif
