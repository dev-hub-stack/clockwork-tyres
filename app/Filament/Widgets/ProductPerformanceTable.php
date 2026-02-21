<?php

namespace App\Filament\Widgets;

use App\Modules\Products\Models\Product;
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
                Product::query()
                    ->select(
                        'products.id',
                        'products.name',
                        'products.sku',
                        'products.price',
                        'brands.name as brand_name',
                        DB::raw('COUNT(DISTINCT order_items.id) as times_sold'),
                        DB::raw('SUM(order_items.quantity) as total_quantity'),
                        DB::raw('SUM(order_items.line_total) as total_revenue'),
                        DB::raw('AVG(order_items.line_total) as avg_line_value'),
                        DB::raw('RANK() OVER (ORDER BY SUM(order_items.line_total) DESC) as revenue_rank')
                    )
                    ->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
                    ->where('orders.external_source', 'tunerstop_historical')
                    ->groupBy('products.id', 'products.name', 'products.sku', 'products.price', 'brands.name')
                    ->orderByDesc('total_revenue')
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
                    ->tooltip(fn ($record) => $record->name),
                
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
                
                Tables\Columns\TextColumn::make('avg_line_value')
                    ->label('Avg Price')
                    ->money('AED')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Current Price')
                    ->money('AED'),
            ])
            ->heading('Product Performance')
            ->defaultSort('total_revenue', 'desc')
            ->paginated([10, 25, 50]);
    }
}
