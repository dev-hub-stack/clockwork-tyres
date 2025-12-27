<?php

namespace App\Filament\Widgets;

use App\Modules\Orders\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class ProductPerformanceTable extends BaseWidget
{
    protected static ?int $sort = 6;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->select(
                        DB::raw('MIN(order_items.id) as id'), // Need an ID for Filament
                        'order_items.sku',
                        'order_items.product_name as name',
                        DB::raw("COALESCE(NULLIF(order_items.brand_name, ''), TRIM(SUBSTRING_INDEX(order_items.product_name, ' - ', 1))) as brand_name"),
                        DB::raw('COUNT(DISTINCT order_items.order_id) as times_sold'),
                        DB::raw('SUM(order_items.quantity) as total_quantity'),
                        DB::raw('SUM(order_items.line_total) as total_revenue'),
                        DB::raw('AVG(order_items.unit_price) as avg_price'),
                        DB::raw('COALESCE(products.price, MAX(order_items.unit_price)) as current_price'),
                        'order_items.product_id'
                    )
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
                    ->where('orders.external_source', 'tunerstop_historical')
                    ->whereNotNull('order_items.product_name')
                    ->where('order_items.product_name', '!=', '')
                    ->groupBy('order_items.sku', 'order_items.product_name', 'order_items.brand_name', 'order_items.product_id', 'products.price')
                    ->orderByRaw('SUM(order_items.line_total) DESC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->name)
                    ->description(fn ($record) => $record->product_id ? '🔗 Linked to inventory' : null),
                
                Tables\Columns\TextColumn::make('brand_name')
                    ->label('Brand')
                    ->searchable()
                    ->badge()
                    ->color('warning'),
                
                Tables\Columns\TextColumn::make('times_sold')
                    ->label('Orders')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Units Sold')
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->money('AED')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('avg_price')
                    ->label('Avg Price')
                    ->money('AED')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('current_price')
                    ->label('Current Price')
                    ->money('AED')
                    ->tooltip(fn ($record) => $record->product_id 
                        ? 'Live price from inventory' 
                        : 'Last sold price (not in inventory)'),
            ])
            ->heading('Product Performance')
            ->defaultSort('total_revenue', 'desc')
            ->paginated([10, 25, 50]);
    }
}
