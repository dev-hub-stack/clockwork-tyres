<?php

namespace App\Filament\Widgets;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsTable extends BaseWidget
{
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->select(
                        'customers.id',
                        'customers.first_name',
                        'customers.last_name',
                        'customers.email',
                        'customers.phone',
                        DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                        DB::raw('SUM(orders.total) as lifetime_value'),
                        DB::raw('AVG(orders.total) as avg_order_value'),
                        DB::raw('MAX(orders.created_at) as last_order_date'),
                        DB::raw('MIN(orders.created_at) as first_order_date'),
                        DB::raw('DATEDIFF(NOW(), MAX(orders.created_at)) as days_since_order')
                    )
                    ->leftJoin('orders', 'customers.id', '=', 'orders.customer_id')
                    ->where('customers.customer_type', 'retail')
                    ->where('customers.email', '!=', 'historical-tunerstop@retail.local')
                    ->groupBy('customers.id', 'customers.first_name', 'customers.last_name', 'customers.email', 'customers.phone')
                    ->orderByDesc('lifetime_value')
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn (Customer $record) => $record->first_name . ' ' . $record->last_name),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->url(fn ($record) => 'mailto:' . $record->email),
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 10 => 'success',
                        $state >= 5 => 'warning',
                        default => 'info'
                    }),
                
                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('Lifetime Value')
                    ->money('AED')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->money('AED')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('first_order_date')
                    ->label('Customer Since')
                    ->dateTime('M d, Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('last_order_date')
                    ->label('Last Order')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->days_since_order < 30 ? 'success' : ($record->days_since_order < 180 ? 'warning' : 'danger')),
            ])
            ->heading('Customer Analytics')
            ->defaultSort('lifetime_value', 'desc')
            ->paginated([10, 25, 50]);
    }
}
