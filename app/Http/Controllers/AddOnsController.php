<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\AddonCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AddOnsController extends Controller
{
    /**
     * Display a listing of addons with category tabs
     */
    public function index(Request $request)
    {
        // Get all active categories for tabs
        $categories = AddonCategory::where('is_active', true)
            ->sorted()
            ->get();

        // Get selected category (default to first category)
        $selectedCategorySlug = $request->get('category', $categories->first()->slug ?? 'wheel-accessories');
        $selectedCategory = AddonCategory::where('slug', $selectedCategorySlug)->first();

        if (!$selectedCategory) {
            abort(404, 'Category not found');
        }

        // Build query for addons
        $query = Addon::where('addon_category_id', $selectedCategory->id)
            ->with('category');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Paginate results
        $addons = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('addons.index', compact('categories', 'selectedCategory', 'addons'));
    }

    /**
     * Show the form for creating a new addon
     */
    public function create(Request $request)
    {
        $categories = AddonCategory::where('is_active', true)->sorted()->get();
        
        // Get selected category from request or default to first
        $selectedCategorySlug = $request->get('category', $categories->first()->slug ?? null);
        $selectedCategory = AddonCategory::where('slug', $selectedCategorySlug)->first();

        return view('addons.create', compact('categories', 'selectedCategory'));
    }

    /**
     * Store a newly created addon
     */
    public function store(Request $request)
    {
        $category = AddonCategory::findOrFail($request->addon_category_id);

        // Base validation rules
        $rules = [
            'addon_category_id' => 'required|exists:addon_categories,id',
            'title' => 'required|string|max:180',
            'part_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'tax_inclusive' => 'boolean',
            'stock_status' => 'required|integer',
            'total_quantity' => 'required|integer|min:0',
            'image_1' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_2' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];

        // Add category-specific validation rules
        foreach ($category->required_fields as $field) {
            $rules[$field] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Handle image uploads
        if ($request->hasFile('image_1')) {
            $validated['image_1'] = $this->uploadImage($request->file('image_1'));
        }
        if ($request->hasFile('image_2')) {
            $validated['image_2'] = $this->uploadImage($request->file('image_2'));
        }

        $addon = Addon::create($validated);

        return redirect()
            ->route('addons.index', ['category' => $category->slug])
            ->with('success', 'Addon created successfully!');
    }

    /**
     * Display the specified addon
     */
    public function show(Addon $addon)
    {
        return view('addons.show', compact('addon'));
    }

    /**
     * Show the form for editing the specified addon
     */
    public function edit(Addon $addon)
    {
        $categories = AddonCategory::where('is_active', true)->sorted()->get();
        $selectedCategory = $addon->category;

        return view('addons.edit', compact('addon', 'categories', 'selectedCategory'));
    }

    /**
     * Update the specified addon
     */
    public function update(Request $request, Addon $addon)
    {
        $category = AddonCategory::findOrFail($request->addon_category_id);

        // Base validation rules
        $rules = [
            'addon_category_id' => 'required|exists:addon_categories,id',
            'title' => 'required|string|max:180',
            'part_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'tax_inclusive' => 'boolean',
            'stock_status' => 'required|integer',
            'total_quantity' => 'required|integer|min:0',
            'image_1' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'image_2' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];

        // Add category-specific validation rules
        foreach ($category->required_fields as $field) {
            $rules[$field] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Handle image uploads
        if ($request->hasFile('image_1')) {
            // Delete old image if exists
            if ($addon->image_1) {
                Storage::disk('s3')->delete($addon->image_1);
            }
            $validated['image_1'] = $this->uploadImage($request->file('image_1'));
        }
        if ($request->hasFile('image_2')) {
            // Delete old image if exists
            if ($addon->image_2) {
                Storage::disk('s3')->delete($addon->image_2);
            }
            $validated['image_2'] = $this->uploadImage($request->file('image_2'));
        }

        $addon->update($validated);

        return redirect()
            ->route('addons.index', ['category' => $category->slug])
            ->with('success', 'Addon updated successfully!');
    }

    /**
     * Remove the specified addon
     */
    public function destroy(Addon $addon)
    {
        $categorySlug = $addon->category->slug;

        // Delete images from S3
        if ($addon->image_1) {
            Storage::disk('s3')->delete($addon->image_1);
        }
        if ($addon->image_2) {
            Storage::disk('s3')->delete($addon->image_2);
        }

        $addon->delete();

        return redirect()
            ->route('addons.index', ['category' => $categorySlug])
            ->with('success', 'Addon deleted successfully!');
    }

    /**
     * Bulk delete addons
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'addon_ids' => 'required|array',
            'addon_ids.*' => 'exists:addons,id',
        ]);

        $addons = Addon::whereIn('id', $request->addon_ids)->get();
        
        foreach ($addons as $addon) {
            // Delete images
            if ($addon->image_1) {
                Storage::disk('s3')->delete($addon->image_1);
            }
            if ($addon->image_2) {
                Storage::disk('s3')->delete($addon->image_2);
            }
            $addon->delete();
        }

        return redirect()
            ->back()
            ->with('success', count($request->addon_ids) . ' addons deleted successfully!');
    }

    /**
     * Show bulk image upload form
     */
    public function showBulkImageUpload(Request $request)
    {
        $categorySlug = $request->get('category', 'wheel-accessories');
        $category = AddonCategory::where('slug', $categorySlug)->first();
        
        if (!$category) {
            abort(404, 'Category not found');
        }

        $addons = Addon::where('addon_category_id', $category->id)
            ->orderBy('part_number')
            ->get();

        return view('addons.bulk-upload-images', compact('category', 'addons'));
    }

    /**
     * Process bulk image upload
     */
    public function processBulkImageUpload(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $uploadedCount = 0;

        foreach ($request->file('images') as $image) {
            $originalName = $image->getClientOriginalName();
            
            // Try to match by part number (filename without extension)
            $partNumber = pathinfo($originalName, PATHINFO_FILENAME);
            
            $addon = Addon::where('part_number', $partNumber)->first();
            
            if ($addon) {
                // Upload and update first available image slot
                $imagePath = $this->uploadImage($image);
                
                if (!$addon->image_1) {
                    $addon->update(['image_1' => $imagePath]);
                } elseif (!$addon->image_2) {
                    $addon->update(['image_2' => $imagePath]);
                } else {
                    // Replace image_1 if both slots are full
                    Storage::disk('s3')->delete($addon->image_1);
                    $addon->update(['image_1' => $imagePath]);
                }
                
                $uploadedCount++;
            }
        }

        return redirect()
            ->back()
            ->with('success', "{$uploadedCount} images uploaded successfully!");
    }

    /**
     * Export addons to CSV
     */
    public function export(Request $request)
    {
        $categorySlug = $request->get('category', 'wheel-accessories');
        $category = AddonCategory::where('slug', $categorySlug)->firstOrFail();

        $addons = Addon::where('addon_category_id', $category->id)->get();

        $filename = "addons_{$category->slug}_" . now()->format('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Headers (category-specific)
        $headers = array_merge(
            ['ID', 'Title', 'Part Number', 'Description', 'Price', 'Wholesale Price', 'Tax Inclusive', 'Stock Status', 'Quantity'],
            $category->csv_fields,
            ['Image 1', 'Image 2', 'Created At', 'Updated At']
        );

        fputcsv($handle, $headers);

        // CSV Rows
        foreach ($addons as $addon) {
            $row = [
                $addon->id,
                $addon->title,
                $addon->part_number,
                $addon->description,
                $addon->price,
                $addon->wholesale_price,
                $addon->tax_inclusive ? 'Yes' : 'No',
                $addon->stock_status,
                $addon->total_quantity,
            ];

            // Add category-specific fields
            foreach ($category->csv_fields as $field) {
                $row[] = $addon->$field ?? '';
            }

            $row[] = $addon->image_1_url;
            $row[] = $addon->image_2_url;
            $row[] = $addon->created_at->format('Y-m-d H:i:s');
            $row[] = $addon->updated_at->format('Y-m-d H:i:s');

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get category-specific fields for dynamic form rendering
     */
    public function getCategoryFields(AddonCategory $category)
    {
        return response()->json([
            'allowed_fields' => $category->allowed_fields,
            'required_fields' => $category->required_fields,
            'csv_fields' => $category->csv_fields,
            'filters' => $category->filters,
        ]);
    }

    /**
     * Upload image to S3
     */
    private function uploadImage($file)
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = 'addons/' . $filename;
        
        Storage::disk('s3')->put($path, file_get_contents($file), 'public');
        
        return $path;
    }
}
