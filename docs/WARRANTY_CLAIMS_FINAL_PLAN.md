# Warranty Claims Module - FINAL IMPLEMENTATION PLAN

**Updated:** November 1, 2025  
**Status:** READY TO BUILD  
**Pattern:** Same as Quote/Invoice/Consignment

---

## ✅ CLIENT DECISIONS CONFIRMED

### 1. Invoice Linking
- ✅ **Optional** - Can create claim without invoice
- ✅ **Cannot change invoice after linking** - Locked once saved
- ✅ **Auto-fetch products** - When invoice selected, show all invoice items
- ✅ **User selects which to claim** - Checkbox selection, not all auto-added

### 2. Warranty Period
- ✅ **External/Manual** - No automatic validation
- ✅ **Manufacturer responsibility** - System doesn't check warranty dates
- ✅ **User handles validation** - Sales rep verifies before processing

### 3. UX Approach
- ✅ **Option A: Filament Timeline + Modal** - Selected!
- Latest 5 activities shown on main view
- "View Full History" opens modal with infinite scroll
- Professional, clean, mobile-friendly

---

## 🎯 CREATE CLAIM WORKFLOW (Based on New Screenshot)

### Screen Layout (Matching Screenshot):
```
┌─────────────────────────────────────────────────────────────┐
│ ← Create Warranty Claim        [🗑️ Delete] [Save Draft] [Save]│
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Number: [2390469] (auto-generated)                          │
│                                                               │
│  Customer*: [Required ▼]                                     │
│                                                               │
│  Invoice (optional): [Select Invoice ▼] ← NEW!              │
│    └─ When selected: Shows "Fetch Products" button          │
│                                                               │
│  Date*: [2025-04-09] 📅                                      │
│                                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ DESCRIPTION*   WAREHOUSE*   QTY*                     │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │ Brand: [Auto-filled]                                 │   │
│  │ Model: [Auto-filled]                                 │   │
│  │ Finish: [Auto-filled]                                │   │
│  │ SKU: [Auto-filled]                                   │   │
│  │ Size: [Auto-filled]                                  │   │
│  │ Bolt Pattern: [Auto-filled]                          │   │
│  │ Offset: [Auto-filled]                                │   │
│  │                                                       │   │
│  │ [✓ Wheels]                                           │   │
│  │                                                       │   │
│  │ "adding item by part number (only inventory items)" │   │
│  │ "all details pre-saved"                              │   │
│  │                                                       │   │
│  │ [Warehouse ▼]     [1]                                │   │
│  │                                                       │   │
│  │ Issue Description: [Textarea]                        │   │
│  │ Resolution: [Replace ▼]                              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                               │
│  [+ Add line]                                                │
│                                                               │
│  Notes:                                                      │
│  [Large textarea]                                            │
│                                                               │
│  Note shown:                                                 │
│  "warehouse drop down will comprise of warehouse names -     │
│   only in stock items for warranty claim"                    │
│                                                               │
│  "Warranty claim invoices will remove the count from         │
│   inventory once marked as replaced"                         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### When "Fetch Products from Invoice" Clicked:
```
┌──────────────────────────────────────────────┐
│ Select Products to Claim                 [✕] │
├──────────────────────────────────────────────┤
│                                               │
│ Invoice: INV-2025-0021                       │
│ Date: April 15, 2025                         │
│ Total: $5,420.00                             │
│                                               │
│ Select items to add to warranty claim:       │
│                                               │
│ [✓] Rotiform BLQ 20x9 Gloss Black (Qty: 4)  │
│     Original Price: $350 each                │
│                                               │
│ [ ] Center Caps Set (Qty: 1)                │
│     Original Price: $120                     │
│                                               │
│ [✓] Lug Nuts Black (Qty: 1)                 │
│     Original Price: $80                      │
│                                               │
│ [ ] TPMS Sensors (Qty: 4)                   │
│     Original Price: $45 each                 │
│                                               │
├──────────────────────────────────────────────┤
│ [Cancel]              [Add Selected (2)]     │
└──────────────────────────────────────────────┘
```

---

## 📋 UPDATED IMPLEMENTATION CHECKLIST

### Phase 1: Database & Models (5-6 hours)

#### Task 1.1: Create Enums (30 minutes)
```bash
mkdir -p app/Modules/Warranties/Enums
```

- [ ] `app/Modules/Warranties/Enums/WarrantyClaimStatus.php`
```php
<?php

namespace App\Modules\Warranties\Enums;

enum WarrantyClaimStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case REPLACED = 'replaced';
    case CLAIMED = 'claimed';
    case RETURNED = 'returned';
    case VOID = 'void';
    
    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::REPLACED => 'Replaced',
            self::CLAIMED => 'Claimed',
            self::RETURNED => 'Returned',
            self::VOID => 'Void',
        };
    }
    
    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::REPLACED => 'warning',
            self::CLAIMED => 'success',
            self::RETURNED => 'info',
            self::VOID => 'danger',
        };
    }
}
```

- [ ] `app/Modules/Warranties/Enums/ClaimActionType.php`
```php
<?php

namespace App\Modules\Warranties\Enums;

enum ClaimActionType: string
{
    case NOTE_ADDED = 'note_added';
    case VIDEO_LINK_ADDED = 'video_link_added';
    case STATUS_CHANGED = 'status_changed';
    case FILE_ATTACHED = 'file_attached';
    case EMAIL_SENT = 'email_sent';
    case CREATED = 'created';
    case RESOLVED = 'resolved';
}
```

- [ ] `app/Modules/Warranties/Enums/ResolutionAction.php`
```php
<?php

namespace App\Modules\Warranties\Enums;

enum ResolutionAction: string
{
    case REPLACE = 'replace';
    case REFUND = 'refund';
    case REPAIR = 'repair';
    case NO_ACTION = 'no_action';
    
    public function getLabel(): string
    {
        return match($this) {
            self::REPLACE => 'Replace Item',
            self::REFUND => 'Refund Customer',
            self::REPAIR => 'Repair/Fix',
            self::NO_ACTION => 'No Action Needed',
        };
    }
}
```

#### Task 1.2: Create Migrations (1.5 hours)

- [ ] Create migration: `2025_11_02_000001_create_warranty_claims_table.php`
```php
Schema::create('warranty_claims', function (Blueprint $table) {
    $table->id();
    $table->string('claim_number')->unique()->index();
    
    // Relationships
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('warehouse_id')->constrained();
    $table->foreignId('representative_id')->nullable()->constrained('users');
    $table->foreignId('invoice_id')->nullable()->constrained('orders'); // OPTIONAL
    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('resolved_by')->nullable()->constrained('users');
    
    // Status
    $table->string('status')->default('draft'); // WarrantyClaimStatus enum
    
    // Dates
    $table->date('issue_date');
    $table->date('claim_date');
    $table->date('resolution_date')->nullable();
    
    // Notes
    $table->text('notes')->nullable();
    $table->text('internal_notes')->nullable();
    
    $table->timestamps();
    $table->softDeletes();
});
```

- [ ] Create migration: `2025_11_02_000002_create_warranty_claim_items_table.php`
```php
Schema::create('warranty_claim_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
    
    // Product reference (from invoice or manual)
    $table->foreignId('product_id')->nullable()->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    
    // Invoice reference (if claim linked to invoice)
    $table->foreignId('invoice_id')->nullable()->constrained('orders');
    $table->foreignId('invoice_item_id')->nullable()->constrained('order_items');
    
    // Claim details
    $table->integer('quantity');
    $table->text('issue_description');
    $table->string('resolution_action'); // ResolutionAction enum
    
    $table->timestamps();
});
```

- [ ] Create migration: `2025_11_02_000003_create_warranty_claim_history_table.php`
```php
Schema::create('warranty_claim_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    
    $table->string('action_type'); // ClaimActionType enum
    $table->text('description');
    $table->json('metadata')->nullable(); // For video URLs, file paths, etc.
    
    $table->timestamps();
    
    $table->index(['warranty_claim_id', 'created_at']);
});
```

- [ ] Run migrations:
```bash
php artisan migrate
```

#### Task 1.3: Create Models (2-3 hours)

- [ ] `app/Modules/Warranties/Models/WarrantyClaim.php`
```php
<?php

namespace App\Modules\Warranties\Models;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Warranties\Enums\WarrantyClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarrantyClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'claim_number',
        'customer_id',
        'warehouse_id',
        'representative_id',
        'invoice_id', // OPTIONAL
        'status',
        'issue_date',
        'claim_date',
        'resolution_date',
        'notes',
        'internal_notes',
        'created_by',
        'resolved_by',
    ];

    protected $casts = [
        'status' => WarrantyClaimStatus::class,
        'issue_date' => 'date',
        'claim_date' => 'date',
        'resolution_date' => 'date',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarrantyClaimItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WarrantyClaimHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeRecent($query)
    {
        return $query->latest('claim_date');
    }

    public function scopeByStatus($query, WarrantyClaimStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', WarrantyClaimStatus::PENDING);
    }

    // Helper Methods
    public function addHistory(ClaimActionType $type, string $description, ?array $metadata = null): void
    {
        $this->histories()->create([
            'user_id' => auth()->id(),
            'action_type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
```

- [ ] `app/Modules/Warranties/Models/WarrantyClaimItem.php`
```php
<?php

namespace App\Modules\Warranties\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Warranties\Enums\ResolutionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimItem extends Model
{
    protected $fillable = [
        'warranty_claim_id',
        'product_id',
        'product_variant_id',
        'invoice_id',
        'invoice_item_id',
        'quantity',
        'issue_description',
        'resolution_action',
    ];

    protected $casts = [
        'resolution_action' => ResolutionAction::class,
        'quantity' => 'integer',
    ];

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'invoice_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'invoice_item_id');
    }
}
```

- [ ] `app/Modules/Warranties/Models/WarrantyClaimHistory.php`
```php
<?php

namespace App\Modules\Warranties\Models;

use App\Models\User;
use App\Modules\Warranties\Enums\ClaimActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimHistory extends Model
{
    protected $fillable = [
        'warranty_claim_id',
        'user_id',
        'action_type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'action_type' => ClaimActionType::class,
        'metadata' => 'array',
    ];

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Task 1.4: Verify Phase 1 (30 minutes)
```bash
php artisan tinker
>>> \App\Modules\Warranties\Models\WarrantyClaim::count()
>>> $claim = new \App\Modules\Warranties\Models\WarrantyClaim()
>>> $claim->getFillable()
```

---

### Phase 2: Filament Resource - CREATE FORM (8-10 hours)

#### Task 2.1: Create Base Resource (30 minutes)

- [ ] `app/Filament/Resources/WarrantyClaimResource.php`
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarrantyClaimResource\Pages;
use App\Modules\Warranties\Models\WarrantyClaim;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class WarrantyClaimResource extends Resource
{
    protected static ?string $model = WarrantyClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 6;
    
    protected static ?string $recordTitleAttribute = 'claim_number';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'invoice', 'items', 'histories'])
            ->latest('claim_date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarrantyClaims::route('/'),
            'create' => Pages\CreateWarrantyClaim::route('/create'),
            'view' => Pages\ViewWarrantyClaim::route('/{record}'),
            'edit' => Pages\EditWarrantyClaim::route('/{record}/edit'),
        ];
    }
}
```

#### Task 2.2: Create Form (MATCHES YOUR SCREENSHOT!) (4-5 hours)

- [ ] `app/Filament/Resources/WarrantyClaimResource/Schemas/WarrantyClaimForm.php`
```php
<?php

namespace App\Filament\Resources\WarrantyClaimResource\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Modules\Orders\Models\Order;
use App\Modules\Warranties\Enums\ResolutionAction;

class WarrantyClaimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(3)->schema([
                // Number (auto-generated, read-only)
                TextInput::make('claim_number')
                    ->label('Number')
                    ->default(fn () => self::generateClaimNumber())
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                // Customer (required)
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'business_name')
                    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                    ->required()
                    ->reactive()
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        $record->business_name ?? $record->name ?? 'Unknown'
                    ),

                // Date (required)
                DatePicker::make('claim_date')
                    ->label('Date')
                    ->default(now())
                    ->required(),
            ]),

            // INVOICE SELECTOR (OPTIONAL) ⭐
            Select::make('invoice_id')
                ->label('Link to Invoice (Optional)')
                ->options(function (Get $get) {
                    $customerId = $get('customer_id');
                    if (!$customerId) return [];
                    
                    return Order::where('customer_id', $customerId)
                        ->invoices()
                        ->latest()
                        ->get()
                        ->mapWithKeys(fn ($invoice) => [
                            $invoice->id => "{$invoice->order_number} - " . 
                                          number_format($invoice->total, 2) . " - " .
                                          $invoice->issue_date->format('M d, Y')
                        ]);
                })
                ->searchable()
                ->reactive()
                ->disabled(fn ($record) => $record && $record->invoice_id) // ⭐ CAN'T CHANGE
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    if ($state) {
                        $invoice = Order::find($state);
                        if ($invoice) {
                            $set('warehouse_id', $invoice->warehouse_id);
                        }
                    }
                })
                ->helperText(fn ($record) => 
                    $record && $record->invoice_id 
                        ? '⚠️ Invoice cannot be changed after creation' 
                        : 'Select an invoice to import products'
                ),

            // ITEMS REPEATER (MATCHES SCREENSHOT)
            Repeater::make('items')
                ->relationship()
                ->schema([
                    Grid::make(3)->schema([
                        // Product Details (left column)
                        Section::make('DESCRIPTION')->schema([
                            Placeholder::make('product_details')
                                ->label(false)
                                ->content(function (Get $get) {
                                    $variantId = $get('product_variant_id');
                                    if (!$variantId) return 'Select product...';
                                    
                                    $variant = \App\Modules\Products\Models\ProductVariant::with('product')->find($variantId);
                                    if (!$variant) return '';
                                    
                                    return view('filament.components.product-details', [
                                        'brand' => $variant->product->brand->name ?? '',
                                        'model' => $variant->product->productModel->name ?? '',
                                        'finish' => $variant->finish->name ?? '',
                                        'sku' => $variant->sku ?? '',
                                        'size' => $variant->size ?? '',
                                        'bolt_pattern' => $variant->bolt_pattern ?? '',
                                        'offset' => $variant->offset ?? '',
                                    ]);
                                }),
                            
                            Select::make('product_variant_id')
                                ->label('Product')
                                ->relationship('productVariant', 'sku')
                                ->searchable(['sku', 'part_number'])
                                ->required()
                                ->reactive()
                                ->getOptionLabelFromRecordUsing(fn ($record) => 
                                    "{$record->sku} - {$record->product->brand->name} {$record->product->productModel->name}"
                                ),
                            
                            Hidden::make('invoice_item_id'),
                        ])->columnSpan(1),

                        // Warehouse (middle column)
                        Section::make('WAREHOUSE')->schema([
                            Select::make('warehouse_id')
                                ->label(false)
                                ->relationship('warehouse', 'name')
                                ->required()
                                ->helperText('Only in-stock items for warranty claim'),
                        ])->columnSpan(1),

                        // Quantity (right column)
                        Section::make('QTY')->schema([
                            TextInput::make('quantity')
                                ->label(false)
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ])->columnSpan(1),
                    ]),

                    // Issue Description
                    Textarea::make('issue_description')
                        ->label('Issue Description')
                        ->required()
                        ->rows(3),

                    // Resolution Action
                    Select::make('resolution_action')
                        ->label('Resolution')
                        ->options(ResolutionAction::class)
                        ->default(ResolutionAction::REPLACE)
                        ->required(),
                ])
                ->defaultItems(0)
                ->addActionLabel('+ Add line')
                ->headerActions([
                    // FETCH FROM INVOICE BUTTON ⭐
                    Action::make('fetchFromInvoice')
                        ->label('Fetch Products from Invoice')
                        ->color('primary')
                        ->visible(fn (Get $get) => $get('../../invoice_id') !== null)
                        ->action(function (Get $get, Set $set) {
                            $invoiceId = $get('../../invoice_id');
                            $invoice = Order::with('items.productVariant')->find($invoiceId);
                            
                            // Show modal with checkboxes
                            // User selects which products to claim
                            // Add selected items to repeater
                        })
                        ->modalHeading('Select Products to Claim')
                        ->modalWidth('2xl'),
                ]),

            // NOTES (BOTTOM)
            Textarea::make('notes')
                ->label('Notes')
                ->rows(4)
                ->helperText('Warehouse dropdown will comprise of warehouse names - only in stock items for warranty claim.\n\nWarranty claim invoices will remove the count from inventory once marked as replaced.'),

            Hidden::make('status')->default('draft'),
            Hidden::make('issue_date')->default(now()),
            Hidden::make('created_by')->default(auth()->id()),
        ]);
    }

    protected static function generateClaimNumber(): string
    {
        $latest = \App\Modules\Warranties\Models\WarrantyClaim::latest('id')->first();
        return str_pad(($latest?->id ?? 0) + 1 + 2390000, 7, '0', STR_PAD_LEFT);
    }
}
```

---

## 🎯 KEY IMPLEMENTATION NOTES

### 1. Invoice Linking Behavior
```php
// OPTIONAL - Can be null
->nullable()

// CANNOT CHANGE AFTER CREATION
->disabled(fn ($record) => $record && $record->invoice_id)

// AUTO-POPULATE WAREHOUSE
->afterStateUpdated(function ($state, Set $set) {
    if ($state) {
        $invoice = Order::find($state);
        $set('warehouse_id', $invoice->warehouse_id);
    }
})
```

### 2. Fetch Products from Invoice
```php
// Shows modal with invoice items
// User CHECKS which ones to claim
// Only selected items are added to repeater

Action::make('fetchFromInvoice')
    ->modalContent(function ($get) {
        $invoice = Order::with('items')->find($get('../../invoice_id'));
        return view('filament.modals.select-invoice-items', [
            'items' => $invoice->items
        ]);
    })
```

### 3. Warranty Period = External
```php
// NO automatic validation
// NO warranty_period field
// User manually verifies with manufacturer
// System just tracks claims
```

### 4. Inventory Impact
```php
// Only when marking as "Replaced"
// Deducts from inventory
// Happens in MarkAsReplacedAction
```

---

## ⏱️ UPDATED TIMELINE

**Phase 1:** 5-6 hours (Database & Models) ← START HERE  
**Phase 2:** 8-10 hours (Create Form matching screenshot)  
**Phase 3:** 4-5 hours (View Page with Timeline)  
**Phase 4:** 4-5 hours (Actions: Add Note, Video, Mark as Replaced)  
**Phase 5:** 3-4 hours (Services)  
**Phase 6:** 4-5 hours (PDF & Email)  
**Phase 7:** 4-5 hours (Testing)  
**Phase 8:** 2-3 hours (Documentation)  

**Total:** 34-43 hours (~1 week)

---

## 🚀 READY TO START!

### Immediate Next Steps:

1. **Create directory structure:**
```bash
cd c:\Users\Dell\Documents\reporting-crm
mkdir -p app/Modules/Warranties/{Models,Enums,Services,Mail}
```

2. **Create enums (30 min):**
   - WarrantyClaimStatus.php
   - ClaimActionType.php
   - ResolutionAction.php

3. **Create migrations (1.5 hours):**
   - warranty_claims table
   - warranty_claim_items table
   - warranty_claim_history table

4. **Run migrations:**
```bash
php artisan migrate
```

5. **Create models (2-3 hours):**
   - WarrantyClaim model
   - WarrantyClaimItem model
   - WarrantyClaimHistory model

**Want me to start building Phase 1 now?** 🎯
