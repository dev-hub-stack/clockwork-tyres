<?php

namespace App\Filament\Widgets;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomersTable extends BaseWidget
{
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        $query = Customer::query()
            ->withoutGlobalScopes()
            ->select(
                'customers.id',
                'customers.first_name',
                'customers.last_name',
                'customers.email',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total) as total_revenue'),
                DB::raw('AVG(orders.total) as avg_order_value'),
                DB::raw('MAX(orders.created_at) as last_order_date')
            )
            ->join('orders', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.external_source', 'tunerstop_historical')
            ->where('customers.customer_type', 'retail')
            ->where('customers.email', '!=', 'historical-tunerstop@retail.local')
            ->whereNull('customers.deleted_at')
            ->groupBy('customers.id', 'customers.first_name', 'customers.last_name', 'customers.email');

        return $table
            ->query(
                Customer::query()
                    ->withoutGlobalScopes()
                    ->fromSub($query, 'customers')
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->first_name . ' ' . $record->last_name),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),
                
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->money('AED')
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->money('AED')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('last_order_date')
                    ->label('Last Order')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->description(fn ($record) => $record->last_order_date ? \Carbon\Carbon::parse($record->last_order_date)->diffForHumans() : ''),
            ])
            ->heading('Top 10 Customers by Revenue')
            ->defaultSort('total_revenue', 'desc');
    }
}
