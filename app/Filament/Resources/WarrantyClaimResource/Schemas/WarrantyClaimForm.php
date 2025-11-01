<?php

namespace App\Filament\Resources\WarrantyClaimResource\Schemas;

use App\Modules\Warranties\Enums\ResolutionAction;
use App\Modules\Orders\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class WarrantyClaimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main Information Section
                Section::make('Claim Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('claim_number')
                                ->label('Number')
                                ->disabled()
                                ->dehydrated(false)
                                ->placeholder('Auto-generated')
                                ->helperText('Will be auto-generated upon creation'),
                            
                            Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'business_name')
                                ->searchable(['business_name', 'first_name', 'last_name', 'email'])
                                ->getOptionLabelFromRecordUsing(function ($record) {
                                    $name = $record->business_name ?? ($record->first_name . ' ' . $record->last_name) ?? 'Unknown Customer';
                                    return trim($name);
                                })
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('invoice_id', null))
                                ->helperText('Select customer first'),
                            
                            DatePicker::make('claim_date')
                                ->label('Date')
                                ->default(now())
                                ->required(),
                        ]),
                        
                        // INVOICE SELECTOR (OPTIONAL - Cannot change after creation)
                        Select::make('invoice_id')
                            ->label('Link to Invoice (Optional)')
                            ->options(function (Get $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) return [];
                                
                                return Order::where('customer_id', $customerId)
                                    ->invoices()
                                    ->where('order_status', '!=', 'cancelled')
                                    ->latest()
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($invoice) => [
                                        $invoice->id => sprintf(
                                            '%s - $%s - %s',
                                            $invoice->order_number,
                                            number_format($invoice->total ?? 0, 2),
                                            $invoice->issue_date?->format('M d, Y') ?? 'N/A'
                                        )
                                    ]);
                            })
                            ->searchable()
                            ->live()
                            ->disabled(fn ($record) => $record && $record->invoice_id) // ⭐ CANNOT CHANGE
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state) {
                                    $invoice = Order::find($state);
                                    if ($invoice) {
                                        $set('warehouse_id', $invoice->warehouse_id);
                                        $set('representative_id', $invoice->representative_id);
                                    }
                                }
                            })
                            ->helperText(fn ($record) => 
                                $record && $record->invoice_id 
                                    ? '⚠️ Invoice cannot be changed after creation' 
                                    : 'Select an invoice to import products (optional)'
                            ),
                        
                        Grid::make(2)->schema([
                            Select::make('warehouse_id')
                                ->label('Warehouse')
                                ->relationship('warehouse', 'warehouse_name')
                                ->required()
                                ->preload()
                                ->helperText('Only in-stock items for warranty claim'),
                            
                            Select::make('representative_id')
                                ->label('Sales Representative')
                                ->relationship('representative', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                    ]),

                // ITEMS REPEATER SECTION
                Section::make('Claimed Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                Grid::make(4)->schema([
                                    // Product Selector
                                    Select::make('product_variant_id')
                                        ->label('Product')
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->getSearchResultsUsing(function (string $search) {
                                            return \App\Modules\Products\Models\ProductVariant::query()
                                                ->with(['product.brand', 'product.model', 'finish'])
                                                ->where(function ($query) use ($search) {
                                                    $query->where('sku', 'like', "%{$search}%")
                                                        ->orWhere('part_number', 'like', "%{$search}%")
                                                        ->orWhereHas('product', function ($q) use ($search) {
                                                            $q->whereHas('brand', fn($b) => $b->where('name', 'like', "%{$search}%"))
                                                              ->orWhereHas('model', fn($m) => $m->where('name', 'like', "%{$search}%"));
                                                        });
                                                })
                                                ->limit(50)
                                                ->get()
                                                ->filter(fn($v) => $v->product !== null)
                                                ->mapWithKeys(fn($v) => [
                                                    $v->id => sprintf(
                                                        '%s - %s %s',
                                                        $v->sku ?? 'NO-SKU',
                                                        $v->product->brand?->name ?? 'N/A',
                                                        $v->product->model?->name ?? 'N/A'
                                                    )
                                                ]);
                                        })
                                        ->getOptionLabelUsing(function ($value) {
                                            if (!$value) return 'Unknown';
                                            
                                            $v = \App\Modules\Products\Models\ProductVariant::with(['product.brand', 'product.model'])->find($value);
                                            if (!$v || !$v->product) return 'Unknown Product';
                                            
                                            return sprintf(
                                                '%s - %s %s',
                                                $v->sku ?? 'NO-SKU',
                                                $v->product->brand?->name ?? 'N/A',
                                                $v->product->model?->name ?? 'N/A'
                                            );
                                        })
                                        ->columnSpan(2)
                                        ->helperText('Search by SKU or part number'),
                                    
                                    TextInput::make('quantity')
                                        ->label('Qty')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required()
                                        ->columnSpan(1),
                                    
                                    Select::make('resolution_action')
                                        ->label('Resolution')
                                        ->options(ResolutionAction::class)
                                        ->default(ResolutionAction::REPLACE)
                                        ->required()
                                        ->columnSpan(1),
                                ]),
                                
                                Textarea::make('issue_description')
                                    ->label('Issue Description')
                                    ->required()
                                    ->rows(3)
                                    ->placeholder('Describe the issue with this product...')
                                    ->helperText('Provide detailed description of the problem'),
                                
                                Hidden::make('invoice_id'),
                                Hidden::make('invoice_item_id'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('+ Add line')
                            ->reorderable(false)
                            ->collapsible()
                            ->helperText('Add products to claim. Items can be manually added below.'),
                    ]),

                // NOTES SECTION
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Customer Notes')
                            ->rows(4)
                            ->placeholder('Notes visible to customer...'),
                        
                        Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(4)
                            ->placeholder('Internal notes (not visible to customer)...'),
                    ])
                    ->collapsible(),
                
                // Hidden fields
                Hidden::make('status')->default('draft'),
                Hidden::make('issue_date')->default(now()),
            ]);
    }
}
