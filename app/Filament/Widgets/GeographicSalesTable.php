<?php

namespace App\Filament\Widgets;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class GeographicSalesTable extends BaseWidget
{
    protected static ?int $sort = 7;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->select(
                        'customers.city',
                        DB::raw('customers.city as id'), // Use city as ID to avoid GROUP BY conflicts
                        DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                        DB::raw('SUM(orders.total) as total_revenue'),
                        DB::raw('COUNT(DISTINCT customers.id) as customer_count'),
                        DB::raw('AVG(orders.total) as avg_order_value')
                    )
                    ->leftJoin('orders', 'customers.id', '=', 'orders.customer_id')
                    ->where('customers.customer_type', 'retail')
                    ->whereNotNull('customers.city')
                    ->groupBy('customers.city')
                    ->orderByRaw('SUM(orders.total) DESC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->default('-'),
                
                Tables\Columns\TextColumn::make('customer_count')
                    ->label('Customers')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),
                
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('AED')
                    ->weight('bold')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->money('AED'),
            ])
            ->heading('Sales by Geographic Location');
    }
}
