<?php

namespace App\Http\Controllers;

use App\Filament\Support\PanelAccess;
use Illuminate\Http\Request;
use App\Modules\Products\Models\ProductImage;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;

class ProductImageController extends Controller
{
    protected function authorizeReadAccess(): void
    {
        abort_unless(PanelAccess::canAccessOperationalSurface('view_products'), 403);
    }

    protected function authorizeWriteAccess(): void
    {
        abort_unless(
            PanelAccess::canAccessOperationalSurfaceAny(['create_products', 'edit_products']),
            403
        );
    }

    /**
     * Display the product images index page
     */
    public function index(Request $request)
    {
        $this->authorizeReadAccess();

        $query = ProductImage::with(['brand', 'model', 'finish']);

        // Apply filters
        if ($request->filled('brand')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->brand . '%');
            });
        }

        if ($request->filled('model')) {
            $query->whereHas('model', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->model . '%');
            });
        }

        if ($request->filled('finish')) {
            $query->whereHas('finish', function ($q) use ($request) {
                $q->where('finish', 'like', '%' . $request->finish . '%');
            });
        }

        // Handle sorting
        $orderBy = $request->get('order_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Toggle sort order for next click
        $nextSortOrder = $sortOrder === 'asc' ? 'desc' : 'asc';

        if (in_array($orderBy, ['brand', 'model', 'finish'])) {
            // Sort by relationship
            $query->join($orderBy . 's', 'product_images.' . $orderBy . '_id', '=', $orderBy . 's.id')
                  ->orderBy($orderBy . 's.name', $sortOrder)
                  ->select('product_images.*');
        } else {
            $query->orderBy($orderBy, $sortOrder);
        }

        $images = $query->paginate(15)->appends($request->all());

        return view('products.images.index', compact('images', 'nextSortOrder'));
    }

    /**
     * Show the form for editing a product image
     */
    public function edit($id)
    {
        $this->authorizeReadAccess();

        $image = ProductImage::with(['brand', 'model', 'finish'])->findOrFail($id);
        $brands = Brand::orderBy('name')->get();
        $models = ProductModel::orderBy('name')->get();
        $finishes = Finish::orderBy('finish')->get();

        return view('products.images.edit', compact('image', 'brands', 'models', 'finishes'));
    }

    /**
     * Update the specified product image
     */
    public function update(Request $request, $id)
    {
        $this->authorizeWriteAccess();

        $image = ProductImage::findOrFail($id);

        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'model_id' => 'required|exists:models,id',
            'finish_id' => 'required|exists:finishes,id',
        ]);

        // Handle image uploads (file upload feature - implement later with S3)
        for ($i = 1; $i <= 9; $i++) {
            $imageField = "image_{$i}";
            
            if ($request->hasFile($imageField)) {
                // TODO: Implement S3 upload when AWS SDK is configured
                $file = $request->file($imageField);
                $filename = $file->getClientOriginalName();
                $validated[$imageField] = $filename;
            } elseif ($request->has("remove_{$imageField}") && $request->get("remove_{$imageField}") === '1') {
                // Remove image
                $validated[$imageField] = null;
            }
        }

        $image->update($validated);

        return redirect()->route('admin.products.images.index')
            ->with('success', 'Product images updated successfully!');
    }

    /**
     * Export product images as CSV
     */
    public function export(Request $request)
    {
        $this->authorizeReadAccess();

        $query = ProductImage::with(['brand', 'model', 'finish']);

        // Apply same filters as index
        if ($request->filled('brand')) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->brand . '%');
            });
        }

        if ($request->filled('model')) {
            $query->whereHas('model', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->model . '%');
            });
        }

        if ($request->filled('finish')) {
            $query->whereHas('finish', function ($q) use ($request) {
                $q->where('finish', 'like', '%' . $request->finish . '%');
            });
        }

        $images = $query->get();

        $filename = 'product-images-' . date('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($images) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, ['Brand', 'Model', 'Finish', 'Image1', 'Image2', 'Image3', 'Image4', 'Image5', 'Image6', 'Image7', 'Image8', 'Image9']);

            foreach ($images as $image) {
                fputcsv($file, [
                    $image->brand->name ?? '',
                    $image->model->name ?? '',
                    $image->finish->finish ?? '',
                    $image->image_1 ?? '',
                    $image->image_2 ?? '',
                    $image->image_3 ?? '',
                    $image->image_4 ?? '',
                    $image->image_5 ?? '',
                    $image->image_6 ?? '',
                    $image->image_7 ?? '',
                    $image->image_8 ?? '',
                    $image->image_9 ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk import product images from CSV
     */
    public function bulkImport(Request $request)
    {
        $this->authorizeWriteAccess();

        $request->validate([
            'importFile' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('importFile');
        $path = $file->getRealPath();
        
        $csv = array_map('str_getcsv', file($path));
        $header = array_map('strtolower', array_map('trim', array_shift($csv)));

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($csv as $row) {
            try {
                $data = array_combine($header, $row);

                // Find related records
                $brand = Brand::where('name', $data['brand'])->first();
                $model = ProductModel::where('name', $data['model'])->first();
                $finish = Finish::where('finish', $data['finish'])->first();

                if (!$brand || !$model || !$finish) {
                    $errors[] = "Row skipped: Brand, Model, or Finish not found for {$data['brand']} {$data['model']} {$data['finish']}";
                    continue;
                }

                // Find or create product image
                $productImage = ProductImage::firstOrNew([
                    'brand_id' => $brand->id,
                    'model_id' => $model->id,
                    'finish_id' => $finish->id,
                ]);

                $isNew = !$productImage->exists;

                // Update image fields
                for ($i = 1; $i <= 9; $i++) {
                    $csvKey = 'image' . $i;
                    $dbField = 'image_' . $i;
                    
                    if (isset($data[$csvKey]) && !empty($data[$csvKey])) {
                        // Prepend 'products/' folder path to image name (like Tunerstop)
                        $imageName = trim($data[$csvKey]);
                        $productImage->{$dbField} = 'products/' . $imageName;
                    }
                }

                $productImage->save();

                if ($isNew) {
                    $imported++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing row: " . $e->getMessage();
            }
        }

        $message = "Import completed: {$imported} new records, {$updated} updated.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode('; ', array_slice($errors, 0, 5));
        }

        return redirect()->route('admin.products.images.index')
            ->with('success', $message);
    }
}
