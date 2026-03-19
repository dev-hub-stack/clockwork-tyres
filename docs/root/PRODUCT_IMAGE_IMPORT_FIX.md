# Product Image Bulk Import Fix

**Date:** November 2, 2025  
**Issue:** Product images not saving correctly during CSV bulk upload  
**Status:** ✅ FIXED

---

## Problem Description

When uploading product images via CSV bulk import, the images were not being saved to the `product_images` table correctly. The image paths were missing the required folder prefix.

## Root Cause

The `ProductImageController::bulkImport()` method was saving raw image filenames from the CSV without prepending the folder path. This differed from how Tunerstop handles image paths.

### Tunerstop's Approach (CORRECT)
```php
// In Tunerstop's ProductImageController::bulk_upload()
$pi->{$field} = Product::makeImagePath($item["image{$i}"]);

// Product::makeImagePath() method
public static function makeImagePath($imageName)
{
    if ($imageName) {
        return self::FOLDER . '/' . $imageName; // Returns "products/image-name.jpg"
    }
}

// Where FOLDER constant is:
public const FOLDER = 'products';
```

### Reporting-CRM's Original Code (WRONG)
```php
// Was saving raw filename
$productImage->{$dbField} = $data[$csvKey]; // Saved: "image-name.jpg"
```

## The Fix

Updated `ProductImageController::bulkImport()` to prepend the `products/` folder path:

```php
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
```

## Expected CSV Format

```csv
brand,model,finish,image1,image2,image3,image4,image5,image6,image7,image8,image9
Fuel Off-Road,D531 Hostage,Black Milled,d531-hostage-black-milled-1.jpg,d531-hostage-black-milled-2.jpg,d531-hostage-black-milled-3.jpg,,,,,
```

## Database Storage

After import, the `product_images` table will now correctly store:

```
image_1: "products/d531-hostage-black-milled-1.jpg"
image_2: "products/d531-hostage-black-milled-2.jpg"
image_3: "products/d531-hostage-black-milled-3.jpg"
```

## Files Modified

- `app/Http/Controllers/ProductImageController.php` - Fixed bulkImport() method

## Commit

```
commit 3686016
fix: Prepend 'products/' folder path to image names in bulk import
```

## Testing Steps

1. Prepare a CSV file with brand, model, finish, and image1-9 columns
2. Go to `/admin/products/images`
3. Click "Import CSV"
4. Upload the CSV file
5. Verify records are created in `product_images` table
6. Check that image paths include `products/` prefix

## Related Files

- `app/Http/Controllers/ProductImageController.php` - Main controller
- `app/Modules/Products/Models/ProductImage.php` - Model
- `resources/views/products/images/index.blade.php` - UI view

## Notes

- This matches Tunerstop's behavior exactly
- The `products/` folder is where product images are stored in S3/storage
- All 9 image slots are supported (image_1 through image_9)
- Empty image fields in CSV are skipped (not overwritten with null)

---

**Status:** ✅ Ready for testing with actual CSV data
