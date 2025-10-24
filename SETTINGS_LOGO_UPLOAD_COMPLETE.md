# Settings Logo Upload - Implementation Complete

## Overview
Updated the ManageSettings page to add logo upload capability and removed unnecessary color pickers as requested.

## Changes Made

### 1. Updated `app/Filament/Pages/Settings/ManageSettings.php`

#### Imports
- ✅ Removed: `use Filament\Forms\Components\ColorPicker;`
- ✅ Added: `use Filament\Forms\Components\FileUpload;`

#### Mount Method
Updated to load logo_path from database:
```php
'logo_path' => $companyBranding->logo_path,
```

#### Form Schema
**Section Renamed**: "Branding & Colors" → "Logo & Branding"

**Removed Components**:
- `ColorPicker::make('primary_color')` - Not needed
- `ColorPicker::make('secondary_color')` - Not needed

**Added Components**:
```php
FileUpload::make('logo_path')
    ->label('Company Logo')
    ->image()
    ->maxSize(10240) // 10MB
    ->disk('public')
    ->directory('company-logos')
    ->helperText('Upload your company logo (max 10MB). Recommended size: 200x200px')
    ->columnSpanFull(),
```

#### Save Method
Updated to handle logo upload correctly:
```php
'logo_path' => $data['logo_path'] ?? $companyBranding->logo_path,
```

This preserves existing logo if no new file is uploaded.

## How It Works

### Logo Upload
1. User uploads image through FileUpload component
2. Filament automatically handles:
   - File validation (image type, max 2MB)
   - Saving to `storage/app/public/company-logos/`
   - Generating filename
   - Storing path in database
3. CompanyBranding model's `getLogoUrlAttribute()` accessor returns public URL

### Logo Display
The logo is already integrated into the quote preview template:

```blade
@php
    $branding = App\Modules\Settings\Models\CompanyBranding::getActive();
@endphp

@if($branding->logo_url)
    <img src="{{ $branding->logo_url }}" alt="Logo" class="h-16 w-auto mb-4">
@else
    <div class="text-2xl font-bold">{{ $branding->company_name }}</div>
@endif
```

## Database Schema
No migration needed - `logo_path` column already exists:
```php
$table->string('logo_path')->nullable();
```

## Testing Steps

1. **Upload Logo**:
   - Navigate to Settings page
   - Click on "Logo & Branding" section
   - Upload a company logo (PNG, JPG, or GIF, max 10MB)
   - Click "Save Settings"
   - Verify success notification

2. **Verify Storage**:
   - Check `storage/app/public/company-logos/` directory
   - Logo file should be saved with unique filename
   - Check database `company_branding` table
   - `logo_path` should contain relative path

3. **View Logo in Quote**:
   - Create or view a quote
   - Click "Preview" action to open slide-over
   - Logo should appear in header (h-16 size)
   - If no logo, company name displays instead

4. **Update Logo**:
   - Upload a different logo
   - Old logo should be replaced
   - New logo appears in quote previews

5. **Remove Logo**:
   - Click "X" on uploaded logo in settings
   - Save settings
   - Quote preview should show company name instead

## Files Modified
- `app/Filament/Pages/Settings/ManageSettings.php` (4 changes)

## Integration Points

### Current Integration
- ✅ Quote preview template (`resources/views/filament/resources/quote-resource/preview.blade.php`)
- ✅ CompanyBranding model with `getLogoUrlAttribute()` accessor
- ✅ Settings page form

### Future Integration (Pending)
- 🔄 Invoice preview template (when InvoiceResource is created)
- 🔄 PDF templates for quote/invoice generation
- 🔄 Email templates for sending quotes/invoices

## Benefits

1. **Professional Branding**: Company logo appears on all customer-facing documents
2. **Centralized Management**: Single settings page for all company information
3. **User-Friendly**: Simple file upload with helpful validation messages
4. **Automatic Handling**: Filament manages file storage, validation, and cleanup
5. **Flexible**: Falls back to company name if no logo uploaded

## Next Steps

After this change, continue with:
1. InvoiceResource creation (similar to QuoteResource)
2. PDF generation for Print/Download actions
3. Email templates with logo integration
4. Wafeq accounting integration

## Notes

- Logo storage uses `public` disk (configured in `config/filesystems.php`)
- Files are stored in `storage/app/public/company-logos/`
- Public access requires `php artisan storage:link` (already configured)
- Recommended logo size: 200x200px for best results
- Maximum file size: 10MB
- Accepted formats: JPG, PNG, GIF, SVG
