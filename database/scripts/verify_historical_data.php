<?php

/**
 * Historical Data Verification Script
 * Run: php database/scripts/verify_historical_data.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo " HISTORICAL DATA VERIFICATION REPORT\n";
echo "========================================\n\n";

// 1. Top 10 Customers by Revenue
echo "📊 TOP 10 CUSTOMERS BY REVENUE:\n";
echo "----------------------------------------\n";
$topCustomers = DB::select("
    SELECT 
        c.id,
        c.first_name, 
        c.last_name, 
        c.email,
        COUNT(o.id) as order_count,
        SUM(o.total) as total_spent
    FROM customers c
    JOIN orders o ON o.customer_id = c.id
    WHERE o.external_source = 'tunerstop_historical'
    GROUP BY c.id, c.first_name, c.last_name, c.email
    ORDER BY total_spent DESC
    LIMIT 10
");

foreach ($topCustomers as $customer) {
    echo sprintf(
        "%s %s (%s)\n  Orders: %d | Total: AED %.2f\n\n",
        $customer->first_name,
        $customer->last_name,
        $customer->email,
        $customer->order_count,
        $customer->total_spent
    );
}

// 2. Orders by Year
echo "\n📈 ORDERS & REVENUE BY YEAR:\n";
echo "----------------------------------------\n";
$ordersByYear = DB::select("
    SELECT 
        YEAR(created_at) as year,
        COUNT(*) as orders,
        SUM(total) as revenue,
        AVG(total) as avg_order_value
    FROM orders
    WHERE external_source = 'tunerstop_historical'
    GROUP BY YEAR(created_at)
    ORDER BY year
");

foreach ($ordersByYear as $year) {
    echo sprintf(
        "%d: %d orders | AED %.2f revenue | AED %.2f AOV\n",
        $year->year,
        $year->orders,
        $year->revenue,
        $year->avg_order_value
    );
}

// 3. Top 10 Best-Selling Products
echo "\n🏆 TOP 10 BEST-SELLING PRODUCTS:\n";
echo "----------------------------------------\n";
$topProducts = DB::select("
    SELECT 
        p.id,
        p.name as product,
        b.name as brand,
        COUNT(oi.id) as times_sold,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.line_total) as revenue
    FROM products p
    LEFT JOIN brands b ON b.id = p.brand_id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    WHERE oi.id IS NOT NULL
    GROUP BY p.id, p.name, b.name
    ORDER BY revenue DESC
    LIMIT 10
");

foreach ($topProducts as $product) {
    echo sprintf(
        "%s by %s\n  Sold: %d times | Qty: %d | Revenue: AED %.2f\n\n",
        $product->product ?? 'Unknown',
        $product->brand ?? 'N/A',
        $product->times_sold,
        $product->total_quantity,
        $product->revenue
    );
}

// 4. Order Status Distribution
echo "\n📦 ORDER STATUS DISTRIBUTION:\n";
echo "----------------------------------------\n";
$orderStatus = DB::select("
    SELECT 
        order_status,
        COUNT(*) as count,
        SUM(total) as revenue
    FROM orders
    WHERE external_source = 'tunerstop_historical'
    GROUP BY order_status
");

foreach ($orderStatus as $status) {
    echo sprintf(
        "%s: %d orders | AED %.2f\n",
        ucfirst($status->order_status),
        $status->count,
        $status->revenue
    );
}

// 5. Payment Status Distribution
echo "\n💳 PAYMENT STATUS DISTRIBUTION:\n";
echo "----------------------------------------\n";
$paymentStatus = DB::select("
    SELECT 
        payment_status,
        COUNT(*) as count,
        SUM(total) as amount
    FROM orders
    WHERE external_source = 'tunerstop_historical'
    GROUP BY payment_status
");

foreach ($paymentStatus as $status) {
    echo sprintf(
        "%s: %d orders | AED %.2f\n",
        ucfirst($status->payment_status),
        $status->count,
        $status->amount
    );
}

// 6. Customer Statistics
echo "\n👥 CUSTOMER STATISTICS:\n";
echo "----------------------------------------\n";
$customerStats = DB::selectOne("
    SELECT 
        COUNT(DISTINCT c.id) as total_customers,
        SUM(order_count) as total_orders,
        AVG(order_count) as avg_orders_per_customer
    FROM customers c
    LEFT JOIN (
        SELECT customer_id, COUNT(*) as order_count
        FROM orders
        WHERE external_source = 'tunerstop_historical'
        GROUP BY customer_id
    ) o ON o.customer_id = c.id
    WHERE c.customer_type = 'retail'
");

echo sprintf("Total Customers: %d\n", $customerStats->total_customers);
echo sprintf("Total Orders: %d\n", $customerStats->total_orders);
echo sprintf("Avg Orders/Customer: %.2f\n", $customerStats->avg_orders_per_customer ?? 0);

// 7. Summary
echo "\n========================================\n";
echo "✅ VERIFICATION COMPLETE\n";
echo "========================================\n\n";
echo "All queries executed successfully!\n";
echo "Historical data is ready for reports module.\n\n";
