# Dashboard & Quote Workflow - Reporting CRM v2.0
## Unified Dashboard Design with Quote-to-Order Flow

**Date:** October 20, 2025  
**Based on:** Current system at `C:\Users\Dell\Documents\Reporting\resources\views\admin\dashboard\unified.blade.php`

---

## 🎯 Dashboard Requirements

### **Current Dashboard Analysis**
From `unified.blade.php`, the dashboard shows:
1. ✅ **4 Stats Cards:**
   - Pending Orders
   - Monthly Revenue
   - Today's Orders
   - New Notifications

2. ✅ **Pending Orders Table** with:
   - Date
   - Order Number
   - Customer Name
   - Wheel Brand/Product
   - Vehicle Info
   - Tracking Number
   - Payment Status
   - Channel (Retail/Wholesale)
   - Action Buttons (Download Delivery Note, Invoice, Record Payment, Mark Done)

3. ✅ **Unified View:**
   - Shows orders from ALL channels (Retail, Wholesale, Manual)
   - Real-time updates
   - Quick actions

---

## 📊 New Dashboard Design

### **Filament Dashboard Widget Structure**

```php
<?php
// app/Filament/Widgets/StatsOverview.php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Pending quotes + pending orders
        $pendingQuotes = Quote::whereIn('quote_status', ['draft', 'sent'])->count();
        $pendingOrders = Order::whereIn('order_status', ['pending', 'processing'])->count();
        $totalPending = $pendingQuotes + $pendingOrders;
        
        // Monthly revenue from completed orders
        $monthlyRevenue = Order::where('order_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');
        
        // Today's activity (quotes + orders)
        $todayQuotes = Quote::whereDate('created_at', today())->count();
        $todayOrders = Order::whereDate('created_at', today())->count();
        $todayActivity = $todayQuotes + $todayOrders;
        
        // Notifications (low stock + warranty claims + overdue invoices)
        $lowStockCount = DB::table('product_inventories')
            ->where('available_quantity', '<', 5)
            ->count();
        $pendingWarranty = DB::table('warranty_claims')
            ->where('status', 'submitted')
            ->count();
        $overdueInvoices = DB::table('invoices')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->count();
        $notifications = $lowStockCount + $pendingWarranty + $overdueInvoices;
        
        return [
            Stat::make('Pending Items', $totalPending)
                ->description("{$pendingQuotes} Quotes, {$pendingOrders} Orders")
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),
                
            Stat::make('Monthly Revenue', '$' . number_format($monthlyRevenue, 2))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
                
            Stat::make("Today's Activity", $todayActivity)
                ->description("{$todayQuotes} Quotes, {$todayOrders} Orders")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning')
                ->chart([3, 3, 2, 5, 6, 4, 3]),
                
            Stat::make('Notifications', $notifications)
                ->description("{$lowStockCount} Low Stock, {$pendingWarranty} Warranty, {$overdueInvoices} Overdue")
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('danger')
                ->chart([2, 4, 3, 7, 5, 9, 8]),
        ];
    }
}
```

### **Unified Quotes & Orders Table Widget**

```php
<?php
// app/Filament/Widgets/UnifiedDocumentsTable.php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UnifiedDocumentsTable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Pending Quotes & Orders - All Channels';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Use database view for unified quotes + orders
                DB::table('vw_unified_documents')
                    ->whereIn('status', ['draft', 'sent', 'pending', 'processing'])
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date('j/n/y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'quote' => 'info',
                        'order' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Number')
                    ->searchable()
                    ->url(fn ($record) => $record->document_type === 'quote' 
                        ? route('filament.admin.resources.quotes.view', $record->id)
                        : route('filament.admin.resources.orders.view', $record->id)
                    ),
                    
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->url(fn ($record) => route('filament.admin.resources.customers.view', $record->customer_id)),
                    
                Tables\Columns\TextColumn::make('first_product')
                    ->label('Product')
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('vehicle')
                    ->label('Vehicle')
                    ->getStateUsing(fn ($record) => 
                        $record->vehicle_year && $record->vehicle_make 
                            ? "{$record->vehicle_year} {$record->vehicle_make} {$record->vehicle_model}"
                            : 'N/A'
                    ),
                    
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->badge()
                    ->color('info')
                    ->default('PENDING'),
                    
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'pending' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record) => 
                        $record->outstanding_amount > 0 
                            ? "PARTIAL ($" . number_format($record->outstanding_amount, 2) . " remaining)"
                            : 'PAID'
                    ),
                    
                Tables\Columns\TextColumn::make('external_source')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'retail' => 'primary',
                        'wholesale' => 'info',
                        'b2b' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state ?? 'MANUAL')),
            ])
            ->actions([
                // Quote actions
                Tables\Actions\Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => $record->document_type === 'quote' && $record->status === 'draft')
                    ->action(fn ($record) => $this->sendQuote($record->id)),
                    
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->document_type === 'quote' && $record->status === 'sent')
                    ->action(fn ($record) => $this->approveQuote($record->id)),
                    
                Tables\Actions\Action::make('convert')
                    ->label('Convert to Order')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn ($record) => $record->document_type === 'quote' && $record->status === 'approved')
                    ->action(fn ($record) => $this->convertQuoteToOrder($record->id)),
                
                // Order actions
                Tables\Actions\Action::make('delivery_note')
                    ->label('Delivery')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn ($record) => $record->document_type === 'order')
                    ->url(fn ($record) => route('orders.delivery-note', $record->id))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->visible(fn ($record) => $record->document_type === 'order')
                    ->url(fn ($record) => route('orders.invoice', $record->id))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('payment')
                    ->label('Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->visible(fn ($record) => $record->document_type === 'order' && $record->outstanding_amount > 0)
                    ->modalHeading('Record Payment')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'card' => 'Card',
                                'bank_transfer' => 'Bank Transfer',
                                'check' => 'Check',
                            ])
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $this->recordPayment($record->id, $data)),
                    
                Tables\Actions\Action::make('mark_done')
                    ->label('Done')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->document_type === 'order' && $record->outstanding_amount <= 0)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $this->markOrderDone($record->id)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
    
    protected function sendQuote(int $quoteId): void
    {
        // Logic to send quote
        $quote = Quote::find($quoteId);
        app(SendQuoteAction::class)->execute($quote);
        
        Notification::make()
            ->success()
            ->title('Quote sent successfully')
            ->send();
    }
    
    protected function approveQuote(int $quoteId): void
    {
        // Logic to approve quote
        $quote = Quote::find($quoteId);
        app(ApproveQuoteAction::class)->execute($quote);
        
        Notification::make()
            ->success()
            ->title('Quote approved')
            ->send();
    }
    
    protected function convertQuoteToOrder(int $quoteId): void
    {
        // Logic to convert quote to order
        $quote = Quote::find($quoteId);
        $order = app(ConvertQuoteToOrderAction::class)->execute($quote);
        
        Notification::make()
            ->success()
            ->title('Quote converted to order')
            ->body("Order #{$order->order_number} created")
            ->send();
    }
    
    protected function recordPayment(int $orderId, array $data): void
    {
        // Logic to record payment
        $order = Order::find($orderId);
        app(RecordPaymentAction::class)->execute($order, $data);
        
        Notification::make()
            ->success()
            ->title('Payment recorded')
            ->send();
    }
    
    protected function markOrderDone(int $orderId): void
    {
        // Logic to mark order as done
        $order = Order::find($orderId);
        $order->update(['order_status' => 'completed']);
        
        Notification::make()
            ->success()
            ->title('Order marked as completed')
            ->send();
    }
}
```

---

## 🔄 Quote Workflow

### **Quote Lifecycle**

```
┌─────────────────────────────────────────────────────────┐
│                    QUOTE WORKFLOW                        │
└─────────────────────────────────────────────────────────┘

    [DRAFT]
       │
       ├──> User creates quote with items
       │
       ▼
    [SENT]
       │
       ├──> Email sent to customer
       │    PDF attached
       │    Valid for X days
       │
       ▼
  [APPROVED/REJECTED]
       │
       ├──> If Approved ──┐
       │                  ▼
       │           [CONVERT TO ORDER]
       │                  │
       │                  ├──> Create order in TunerStop/Wholesale
       │                  │    (via API call)
       │                  │
       │                  ▼
       │           [ORDER CREATED]
       │                  │
       │                  ├──> Order syncs back to CRM
       │                  │
       │                  ▼
       │           Quote marked as [CONVERTED]
       │
       └──> If Rejected ──> [REJECTED]
       
    [EXPIRED]
       │
       └──> Auto-expires after validity period
```

### **Quote Actions Class**

```php
<?php
// app/Modules/Quotes/Actions/ConvertQuoteToOrderAction.php

namespace App\Modules\Quotes\Actions;

use App\Modules\Quotes\Models\Quote;
use App\Modules\Orders\Models\Order;
use App\Services\ExternalSystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ConvertQuoteToOrderAction
{
    public function __construct(
        private ExternalSystemAdapter $externalSystem
    ) {}
    
    public function execute(Quote $quote): Order
    {
        // Validate quote is approved
        if ($quote->quote_status !== QuoteStatus::APPROVED) {
            throw new \Exception('Only approved quotes can be converted to orders');
        }
        
        DB::beginTransaction();
        
        try {
            // Step 1: Create order in external system (TunerStop or Wholesale)
            $externalOrderId = $this->createExternalOrder($quote);
            
            // Step 2: Wait for sync or manually create order record
            $order = $this->createOrderFromQuote($quote, $externalOrderId);
            
            // Step 3: Update quote status
            $quote->update([
                'quote_status' => QuoteStatus::CONVERTED,
                'converted_to_order_id' => $order->id,
                'converted_at' => now(),
            ]);
            
            // Step 4: Fire events
            event(new QuoteConverted($quote, $order));
            
            DB::commit();
            
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function createExternalOrder(Quote $quote): string
    {
        $customer = $quote->customer;
        
        // Determine which system to create order in
        $targetSystem = $customer->customer_type === 'retail' ? 'tunerstop' : 'wholesale';
        
        // Prepare order payload
        $payload = [
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'items' => $quote->quoteItems->map(fn($item) => [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->toArray(),
            'quote_reference' => $quote->quote_number,
            'vehicle' => [
                'year' => $quote->vehicle_year,
                'make' => $quote->vehicle_make,
                'model' => $quote->vehicle_model,
            ],
        ];
        
        // Call external API
        $response = $this->externalSystem->createOrder($targetSystem, $payload);
        
        return $response['order_id'];
    }
    
    private function createOrderFromQuote(Quote $quote, string $externalOrderId): Order
    {
        // Create order record (will be updated by sync)
        $order = Order::create([
            'external_id' => $externalOrderId,
            'external_source' => $quote->customer->customer_type === 'retail' ? 'retail' : 'wholesale',
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . str_pad($externalOrderId, 6, '0', STR_PAD_LEFT),
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'order_type' => $quote->customer->customer_type,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => $quote->total_amount,
            'vehicle_year' => $quote->vehicle_year,
            'vehicle_make' => $quote->vehicle_make,
            'vehicle_model' => $quote->vehicle_model,
            'representative_id' => $quote->representative_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Create order items
        foreach ($quote->quoteItems as $quoteItem) {
            $order->orderItems()->create([
                'product_id' => $quoteItem->product_id,
                'product_variant_id' => $quoteItem->product_variant_id,
                'addon_id' => $quoteItem->addon_id,
                'item_type' => $quoteItem->item_type,
                'item_name' => $quoteItem->item_name,
                'sku' => $quoteItem->sku,
                'quantity' => $quoteItem->quantity,
                'unit_price' => $quoteItem->unit_price,
                'discount_amount' => $quoteItem->discount_amount,
                'line_total' => $quoteItem->line_total,
            ]);
        }
        
        return $order;
    }
}
```

---

## 🎨 Dashboard Layout

### **Filament Dashboard Page**

```php
<?php
// app/Filament/Pages/Dashboard.php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.dashboard';
    
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\UnifiedDocumentsTable::class,
            \App\Filament\Widgets\SalesChart::class,
            \App\Filament\Widgets\LatestWarrantyClaims::class,
        ];
    }
    
    public function getColumns(): int | string | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 4,
        ];
    }
}
```

### **Database View for Unified Documents**

```sql
-- Create view to combine quotes and orders
CREATE VIEW vw_unified_documents AS
SELECT 
    'quote' as document_type,
    q.id,
    q.quote_number as document_number,
    q.customer_id,
    c.name as customer_name,
    q.quote_status as status,
    q.total_amount,
    NULL as paid_amount,
    NULL as outstanding_amount,
    NULL as external_source,
    NULL as tracking_number,
    NULL::VARCHAR as payment_status,
    q.vehicle_year,
    q.vehicle_make,
    q.vehicle_model,
    q.representative_id,
    q.created_at,
    q.updated_at,
    (
        SELECT CONCAT(p.name, ' - ', b.name)
        FROM quote_items qi
        LEFT JOIN products p ON qi.product_id = p.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE qi.quote_id = q.id
        ORDER BY qi.id
        LIMIT 1
    ) as first_product
FROM quotes q
LEFT JOIN customers c ON q.customer_id = c.id
WHERE q.deleted_at IS NULL

UNION ALL

SELECT 
    'order' as document_type,
    o.id,
    o.order_number as document_number,
    o.customer_id,
    c.name as customer_name,
    o.order_status as status,
    o.total_amount,
    o.paid_amount,
    o.outstanding_amount,
    o.external_source,
    o.tracking_number,
    o.payment_status,
    o.vehicle_year,
    o.vehicle_make,
    o.vehicle_model,
    o.representative_id,
    o.created_at,
    o.updated_at,
    (
        SELECT CONCAT(oi.item_name, ' - ', b.name)
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE oi.order_id = o.id
        ORDER BY oi.id
        LIMIT 1
    ) as first_product
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.id;
```

---

## 📱 Mobile-Responsive Dashboard

The Filament dashboard is automatically mobile-responsive with:
- Collapsible stats cards
- Horizontal scrolling for tables
- Touch-friendly action buttons
- Adaptive column layout

---

## 🔔 Real-time Notifications

### **Notification Channels**

```php
<?php
// app/Filament/Widgets/NotificationsWidget.php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Modules\Orders\Models\Order;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Warranty\Models\WarrantyClaim;

class NotificationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.notifications';
    
    protected int | string | array $columnSpan = 'full';
    
    public function getNotifications(): array
    {
        return [
            [
                'type' => 'low_stock',
                'count' => $this->getLowStockCount(),
                'icon' => 'heroicon-o-exclamation-triangle',
                'color' => 'warning',
                'message' => 'items are low in stock',
                'action' => route('filament.admin.resources.inventory.index', ['filter' => 'low_stock']),
            ],
            [
                'type' => 'pending_warranty',
                'count' => $this->getPendingWarrantyClaims(),
                'icon' => 'heroicon-o-shield-exclamation',
                'color' => 'danger',
                'message' => 'warranty claims need attention',
                'action' => route('filament.admin.resources.warranty-claims.index', ['filter' => 'pending']),
            ],
            [
                'type' => 'overdue_invoices',
                'count' => $this->getOverdueInvoices(),
                'icon' => 'heroicon-o-document-text',
                'color' => 'danger',
                'message' => 'invoices are overdue',
                'action' => route('filament.admin.resources.invoices.index', ['filter' => 'overdue']),
            ],
            [
                'type' => 'pending_quotes',
                'count' => $this->getPendingQuotes(),
                'icon' => 'heroicon-o-document-duplicate',
                'color' => 'info',
                'message' => 'quotes waiting for approval',
                'action' => route('filament.admin.resources.quotes.index', ['filter' => 'sent']),
            ],
        ];
    }
    
    protected function getLowStockCount(): int
    {
        return DB::table('product_inventories')
            ->where('available_quantity', '<', 5)
            ->count();
    }
    
    protected function getPendingWarrantyClaims(): int
    {
        return WarrantyClaim::where('status', 'submitted')->count();
    }
    
    protected function getOverdueInvoices(): int
    {
        return DB::table('invoices')
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->count();
    }
    
    protected function getPendingQuotes(): int
    {
        return Quote::where('quote_status', 'sent')->count();
    }
}
```

---

## ✅ Summary

### **What's Different from Current System:**

| Current System | New System |
|----------------|------------|
| Mixed quotes/orders in one table | Separate `quotes` and `orders` tables |
| Manual order creation | Quotes convert to orders via API |
| Single dashboard view | Filament widgets with real-time updates |
| Static table | Dynamic table with inline actions |
| Manual refresh | Auto-refresh every 30s |
| Basic stats | Advanced stats with charts |

### **Benefits:**
1. ✅ Clear separation: Quotes vs Orders
2. ✅ Better workflow: Quote → Approval → Conversion → Order
3. ✅ No duplicate data entry
4. ✅ Real-time sync from TunerStop/Wholesale
5. ✅ Same familiar UI as current system
6. ✅ Enhanced with Filament features

---

**END OF DASHBOARD & QUOTE WORKFLOW DOCUMENT**
