<?php

namespace App\Filament\Pages;

use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use Filament\Pages\Page;

class Dashboard extends Page
{
    private const REVENUE_CUTOFF_DATE = '2026-01-01';

    protected string $view = 'filament.pages.dashboard';
    
    protected static ?string $navigationLabel = 'Dashboard';
    
    protected static ?int $navigationSort = -2;
    
    public $pendingOrders;
    public $monthlyRevenue;
    public $todayOrders;
    public $notifications;
    public $orders;
    public $currency = 'AED'; // Default currency

    public function mount(): void
    {
        // Get currency from settings
        try {
            $currencySetting = \DB::table('settings')->where('key', 'site.currency')->first();
            if ($currencySetting) {
                $this->currency = $currencySetting->value ?? 'AED';
            }
        } catch (\Exception $e) {
            $this->currency = 'AED';
        }
        
        // Filter: Only INVOICES (exclude quotes)
        // Pending orders = not yet (delivered AND paid) — i.e. still active
        $this->pendingOrders = Order::where('document_type', DocumentType::INVOICE)
            ->whereIn('order_status', ['pending', 'processing', 'shipped', 'delivered'])
            ->where(fn($q) => $q->where('order_status', '!=', 'delivered')->orWhere('payment_status', '!=', 'paid'))
            ->count();
        
        $this->monthlyRevenue = Order::where('document_type', DocumentType::INVOICE)
            ->whereIn('order_status', ['delivered', 'completed'])
            ->whereDate('created_at', '>=', self::REVENUE_CUTOFF_DATE)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
        
        $this->todayOrders = Order::where('document_type', DocumentType::INVOICE)
            ->whereDate('created_at', today())
            ->count();
        
        $this->notifications = 0;
        try {
            if (\Schema::hasTable('warranty_claims')) {
                $this->notifications = \DB::table('warranty_claims')
                    ->where('status', 'pending')
                    ->count();
            }
        } catch (\Exception $e) {
            // Skip if table doesn't exist
        }

        // Get pending orders with relationships - ONLY INVOICES
        // Exclude orders where delivered + paid (those are "complete")
        $pendingOrdersList = Order::where('document_type', DocumentType::INVOICE)
            ->whereIn('order_status', ['pending', 'processing', 'shipped', 'delivered'])
            ->where(fn($q) => $q->where('order_status', '!=', 'delivered')->orWhere('payment_status', '!=', 'paid'))
            ->with(['customer', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $this->orders = [];
        foreach ($pendingOrdersList as $order) {
            $firstItem = $order->items->first();
            
            // Get wheel brand from order_items.brand_name (snapshot)
            $wheelBrand = 'N/A';
            if ($firstItem) {
                $wheelBrand = $firstItem->brand_name ?? 'N/A';
            }
            
            $vehicle = 'N/A';
            if ($order->vehicle_year || $order->vehicle_make || $order->vehicle_model || $order->vehicle_sub_model) {
                $vehicle = implode(' ', array_filter([
                    $order->vehicle_year,
                    $order->vehicle_make,
                    $order->vehicle_model,
                    $order->vehicle_sub_model,
                ]));
            }
            
            // Collect all items for expandable view
            $orderItems = [];
            foreach ($order->items as $item) {
                // Calculate line total if it's 0
                $unitPrice = (float) ($item->unit_price ?? 0);
                $quantity = $item->quantity ?? 0;
                $lineTotal = (float) ($item->line_total ?? 0);
                
                // If line_total is 0, calculate it
                if ($lineTotal == 0 && $unitPrice > 0) {
                    $lineTotal = $unitPrice * $quantity;
                }
                
                $orderItems[] = [
                    'product_name' => $item->product_name ?? 'N/A',
                    'brand' => $item->brand_name ?? 'N/A',
                    'model' => $item->model_name ?? 'N/A',
                    'sku' => $item->sku ?? 'N/A',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }
            
            $this->orders[] = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->format('n/j/y'),
                'customer_name' => $order->customer ? $order->customer->name : 'Unknown Customer',
                'customer_phone' => $order->customer ? $order->customer->phone : '',
                'customer_email' => $order->customer ? $order->customer->email : '',
                'customer_id' => $order->customer_id,
                'customer_url' => $order->customer_id 
                    ? '/admin/invoices?tableFilters[customer_id][value]=' . $order->customer_id
                    : '#',
                'order_url' => $this->getOrderUrl($order),
                'wheel_brand' => $wheelBrand,
                'vehicle' => $vehicle,
                'tracking_number' => $order->tracking_number,
                'tracking_url'    => $order->tracking_url,
                'order_status'    => $order->order_status ?? 'pending',
                'payment_status'  => $order->payment_status ?? 'pending',
                'outstanding_amount' => (float) ($order->outstanding_amount ?? 0),
                'sub_total' => (float) ($order->sub_total ?? 0),
                'vat' => (float) ($order->vat ?? 0),
                'shipping' => (float) ($order->shipping ?? 0),
                'total' => (float) ($order->total ?? 0),
                'items' => $orderItems,
                'order_notes' => $order->order_notes ?? '',
                'internal_notes' => $order->internal_notes ?? '',
            ];
        }
    }
    
    /**
     * Get the URL for an order based on its document type
     */
    protected function getOrderUrl($order): string
    {
        // Check document type to determine where to navigate
        if ($order->document_type === 'quote') {
            // Go to QuoteResource view page
            return '/admin/quotes/' . $order->id;
        } else {
            // Go to InvoiceResource view page (not edit)
            return '/admin/invoices/' . $order->id;
        }
    }
}
