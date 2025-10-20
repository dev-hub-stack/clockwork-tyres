# Filament v4 - Lessons Learned & Common Issues

**Project:** Reporting CRM  
**Date:** October 21, 2025  
**Module:** Customers Module (First Resource Implementation)  
**Filament Version:** v4.x  
**Laravel Version:** 12.34.0

---

## 🚨 Critical Breaking Changes from Filament v3 to v4

### 1. **Form vs Schema - MAJOR BREAKING CHANGE**

#### ❌ OLD (Filament v3):
```php
use Filament\Forms\Form;

public static function form(Form $form): Form
{
    return $form->schema([
        // components here
    ]);
}
```

#### ✅ NEW (Filament v4):
```php
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        // components here
    ]);
}
```

**Key Changes:**
- Import changed from `Filament\Forms\Form` → `Filament\Schemas\Schema`
- Method changed from `$form->schema()` → `$schema->components()`
- Applies to: Resources, Pages, RelationManagers

---

### 2. **Actions Namespace - Class Not Found Errors**

#### ❌ WRONG:
```php
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\BulkActionGroup;
```

#### ✅ CORRECT:
```php
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
```

**Error Message:**
```
Class "Filament\Tables\Actions\EditAction" not found
```

**Root Cause:**
- In Filament v4, actions are in `Filament\Actions` namespace
- NOT in `Filament\Tables\Actions` (this namespace doesn't exist in v4)

---

### 3. **Table Actions & Bulk Actions Method Names**

#### ❌ OLD (Filament v3):
```php
->actions([
    EditAction::make(),
    DeleteAction::make(),
])
->bulkActions([
    BulkActionGroup::make([
        DeleteBulkAction::make(),
    ]),
])
```

#### ✅ NEW (Filament v4):
```php
->recordActions([
    EditAction::make(),
    DeleteAction::make(),
])
->toolbarActions([
    DeleteBulkAction::make(),
    RestoreBulkAction::make(),
])
```

**Key Changes:**
- `->actions()` → `->recordActions()` (for row-level actions)
- `->bulkActions()` → `->toolbarActions()` (for bulk operations)
- **No BulkActionGroup wrapper needed** in toolbarActions
- `->headerActions()` remains the same (used in RelationManagers)

---

### 4. **Component Namespace Changes**

#### Layout Components:
```php
// ✅ CORRECT
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
```

#### Form Components:
```php
// ✅ CORRECT
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
```

#### Table Components:
```php
// ✅ CORRECT
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
```

**Rule of Thumb:**
- **Layout/Structure:** `Filament\Schemas\Components\*`
- **Form Fields:** `Filament\Forms\Components\*`
- **Table Elements:** `Filament\Tables\Columns\*` & `Filament\Tables\Filters\*`

---

### 5. **Type Declarations for Navigation Properties**

#### ❌ WILL CAUSE TYPE ERRORS:
```php
protected static ?string $navigationIcon = 'heroicon-o-user-group';
protected static ?string $navigationGroup = 'Customers';
```

#### ✅ CORRECT:
```php
use BackedEnum;
use UnitEnum;

protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
protected static string|UnitEnum|null $navigationGroup = 'Customers';
```

**Why:**
- Filament v4 supports Enums for navigation properties
- Type declarations must include `BackedEnum|null` or `UnitEnum|null`
- Import both enum types at the top of the file

---

## 🔧 Step-by-Step Resource Setup Checklist

### For CustomerResource.php (Main Resource):

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Modules\Customers\Models\Customer;

// ✅ Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

// ✅ Schema/Layout Components
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

// ✅ Core Resource
use Filament\Resources\Resource;

// ✅ Table Components
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

// ✅ Actions (CORRECT NAMESPACE)
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;

// ✅ Eloquent
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

// ✅ Enum Types
use UnitEnum;
use BackedEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // ✅ Type declarations with Enum support
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    // ✅ Form method uses Schema, not Form
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer Information')
                ->schema([
                    // fields here
                ]),
        ]);
    }

    // ✅ Table method uses correct action methods
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
            ])
            ->filters([
                SelectFilter::make('customer_type'),
                TrashedFilter::make(),
            ])
            ->recordActions([  // ✅ NOT ->actions()
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([  // ✅ NOT ->bulkActions()
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ]);
    }
}
```

### For RelationManagers:

```php
<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

// ✅ Actions from correct namespace
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // fields
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // columns
            ])
            ->headerActions([  // ✅ Correct for RelationManagers
                CreateAction::make(),
            ])
            ->recordActions([  // ✅ NOT ->actions()
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([  // ✅ NOT ->bulkActions()
                DeleteBulkAction::make(),
            ]);
    }
}
```

---

## 🐛 Common Error Messages & Solutions

### Error 1: Class "Filament\Tables\Actions\EditAction" not found

**Solution:**
```php
// ❌ Remove this
use Filament\Tables\Actions\EditAction;

// ✅ Add this
use Filament\Actions\EditAction;
```

### Error 2: Call to undefined method Schema::schema()

**Solution:**
```php
// ❌ Change this
return $schema->schema([...]);

// ✅ To this
return $schema->components([...]);
```

### Error 3: Type error for $navigationIcon property

**Solution:**
```php
// ✅ Add these imports
use BackedEnum;
use UnitEnum;

// ✅ Update type declaration
protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
```

### Error 4: Actions not showing in table

**Solution:**
```php
// ❌ Wrong method name
->actions([...])

// ✅ Correct method name
->recordActions([...])
```

### Error 5: Bulk actions not working

**Solution:**
```php
// ❌ Wrong structure
->bulkActions([
    BulkActionGroup::make([
        DeleteBulkAction::make(),
    ]),
])

// ✅ Correct structure
->toolbarActions([
    DeleteBulkAction::make(),
])
```

---

## 📋 Pre-Flight Checklist Before Creating Resources

- [ ] Check Filament version (`composer show filament/filament`)
- [ ] Use `Schema` not `Form` for form() method
- [ ] Import actions from `Filament\Actions` namespace
- [ ] Use `->recordActions()` instead of `->actions()`
- [ ] Use `->toolbarActions()` instead of `->bulkActions()`
- [ ] Add `BackedEnum|null` and `UnitEnum|null` type declarations
- [ ] Import layout components from `Filament\Schemas\Components`
- [ ] Import form fields from `Filament\Forms\Components`
- [ ] Run `php artisan optimize:clear` after making changes
- [ ] Test in browser before committing

---

## 🎯 Testing Strategy

### 1. After Creating Resource Files:
```bash
php artisan optimize:clear
```

### 2. Test in This Order:
1. ✅ List page loads (`/admin/customers`)
2. ✅ Create form works (click "New Customer")
3. ✅ Edit form works (click edit on a record)
4. ✅ Delete works (soft delete)
5. ✅ Filters work (customer type, status)
6. ✅ Bulk actions work (select multiple, delete)
7. ✅ Relation managers load (click on a customer → check tabs)
8. ✅ Relation manager CRUD works (add/edit/delete addresses)

### 3. Common Cache Issues:
If changes don't reflect:
```bash
php artisan optimize:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

---

## 📚 Reference Implementation

**Working Example:** `app/Filament/Resources/Settings/CurrencySettings/Tables/CurrencySettingsTable.php`

This was created earlier in the Settings Module and uses the correct Filament v4 patterns.

---

## 🚀 Migration Path from v3 to v4

If you have existing Filament v3 resources:

1. **Update imports:**
   - `Filament\Forms\Form` → `Filament\Schemas\Schema`
   - `Filament\Tables\Actions\*` → `Filament\Actions\*`
   - Add `Filament\Schemas\Components\*` for layouts

2. **Update method signatures:**
   - `form(Form $form): Form` → `form(Schema $schema): Schema`
   - `$form->schema([...])` → `$schema->components([...])`

3. **Update table methods:**
   - `->actions([...])` → `->recordActions([...])`
   - `->bulkActions([...])` → `->toolbarActions([...])`
   - Remove `BulkActionGroup::make()` wrapper

4. **Update type declarations:**
   - Add `BackedEnum|null` to navigation properties

5. **Clear cache:**
   ```bash
   php artisan optimize:clear
   ```

---

## 💡 Best Practices

1. **Always check Settings resources first** - They were created correctly and can serve as templates
2. **Test backend logic before UI** - Verify models, services, and actions work via tinker or test scripts
3. **Use semantic search** - Look for existing patterns in the codebase before creating new resources
4. **Read error messages carefully** - Filament v4 errors clearly point to namespace issues
5. **Keep cache clear during development** - Run `optimize:clear` frequently

---

## 📝 Summary

**Total Issues Fixed:** 5 major breaking changes
**Time Lost:** ~30 minutes debugging namespace issues
**Time Saved for Future Modules:** ~2-3 hours per module

**Key Takeaway:**
Filament v4 is NOT backward compatible with v3 in several critical areas. Always:
1. Use `Schema` not `Form`
2. Import actions from `Filament\Actions`
3. Use `recordActions()` and `toolbarActions()`
4. Add proper type declarations with Enum support

---

**Created by:** GitHub Copilot  
**Module:** Customers Module  
**Status:** ✅ All issues resolved, UI fully functional
