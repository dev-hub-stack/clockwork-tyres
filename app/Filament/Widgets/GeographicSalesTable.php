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
        $query = Customer::query()
            ->withoutGlobalScopes() // Prevent SoftDeletes from adding where customers.deleted_at is null to the subquery
            ->select(
                'customers.city',
                DB::raw('customers.city as id'),
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('SUM(orders.total) as total_revenue'),
                DB::raw('COUNT(DISTINCT customers.id) as customer_count'),
                DB::raw('AVG(orders.total) as avg_order_value')
            )
            ->leftJoin('orders', 'customers.id', '=', 'orders.customer_id')
            ->where('customers.customer_type', 'retail')
            ->whereNotNull('customers.city')
            ->whereNull('customers.deleted_at') // Manually add it inside the subquery
            ->groupBy('customers.city');

        return $table
            ->query(
                Customer::query()
                    ->withoutGlobalScopes() // Prevent SoftDeletes from adding where customers.deleted_at is null to the outer query
                    ->fromSub($query, 'customers')
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
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->money('AED')
                    ->sortable(),
            ])
            ->heading('Sales by Geographic Location')
            ->defaultSort('total_revenue', 'desc');
    }
}
