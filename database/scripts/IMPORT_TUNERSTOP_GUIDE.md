# TunerStop Historical Data Import Guide

## Overview

This guide explains how to import historical order data from the TunerStop e-commerce database (`tunerstop-data.sql`) into the Reporting CRM for generating comprehensive reports from 2020 onwards.

## Data Period

- **Start**: October 2020
- **End**: Present (December 2025)
- **Total Orders**: ~3,000+ orders with ~12,000+ order items
- **Products**: ~19,000+ products with ~370,000+ variants

## Prerequisites

1. **MySQL Server** - To restore the TunerStop dump
2. **PHP 8.1+** with MySQL PDO extension
3. **Reporting CRM** properly configured and migrated

## Step 1: Restore TunerStop Database

```bash
# Create a database for the dump
mysql -u root -p -e "CREATE DATABASE tunerstop_source CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import the dump file
mysql -u root -p tunerstop_source < tunerstop-data.sql
```

**Note**: The dump is ~108MB, import may take a few minutes.

## Step 2: Configure Database Connection

Add these to your `.env` file:

```env
# TunerStop Source Database (for historical import)
TUNERSTOP_DB_HOST=127.0.0.1
TUNERSTOP_DB_PORT=3306
TUNERSTOP_DB_DATABASE=tunerstop_source
TUNERSTOP_DB_USERNAME=root
TUNERSTOP_DB_PASSWORD=your_password
```

## Step 3: Run the Import

### Option A: Full Import (Recommended)

```bash
php artisan import:tunerstop-historical
```

This will import:
- ✅ Brands (~400)
- ✅ Models (~4,700)
- ✅ Finishes
- ✅ Products (~19,000)
- ✅ Product Variants (~370,000)
- ✅ Orders with order items

### Option B: Orders Only (If products already synced)

```bash
php artisan import:tunerstop-historical --skip-products
```

### Option C: Dry Run (Test without saving)

```bash
php artisan import:tunerstop-historical --dry-run
```

### Option D: Import Specific Date Range

```bash
# Import only 2024 orders
php artisan import:tunerstop-historical --from-date=2024-01-01 --to-date=2024-12-31

# Import only orders from 2023 onwards
php artisan import:tunerstop-historical --from-date=2023-01-01
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--connection` | Source database connection name | `tunerstop_source` |
| `--batch-size` | Records per batch | `500` |
| `--skip-products` | Skip importing products/variants | `false` |
| `--orders-only` | Only import orders (skip all reference data) | `false` |
| `--dry-run` | Simulate without saving | `false` |
| `--from-date` | Start date filter (Y-m-d) | None |
| `--to-date` | End date filter (Y-m-d) | None |

## Data Mapping

### Order Status Mapping

| TunerStop Status | CRM Status |
|------------------|------------|
| -1 (Not Fulfilled) | PENDING |
| 0 (Pending) | PENDING |
| 1 (Completed) | COMPLETED |
| 2 (Rejected) | CANCELLED |

### Product Name Parsing

The import parses product names to extract brand, model, and finish:

```
"JR Wheels - JR30 Platinum Red"
  → Brand: "JR Wheels"
  → Model: "JR30"
  → Finish: "Platinum Red"

"BLACK RHINO - CHAMBER Matte Black"
  → Brand: "BLACK RHINO"
  → Model: "CHAMBER"
  → Finish: "Matte Black"
```

### Data Preserved in Snapshots

Order items store historical data in JSON snapshots:

```json
{
  "product_snapshot": {
    "external_product_id": 1417,
    "name": "Stealth Custom Series - K5 Matte Jet Black",
    "brand_name": "Stealth Custom Series",
    "model_name": "K5",
    "finish_name": "Matte Jet Black",
    "retail_price": 2600,
    "snapshot_date": "2020-10-22 07:46:53",
    "source": "tunerstop_historical"
  },
  "variant_snapshot": {
    "size": "8.5x17",
    "diameter": "17",
    "width": "8.5",
    "bolt_pattern": "5x127",
    "offset": "-10"
  }
}
```

## After Import

### Verify Data

```bash
# Check imported counts
php artisan tinker

# In tinker:
Order::where('external_source', 'tunerstop_historical')->count();
OrderItem::whereHas('order', fn($q) => $q->where('external_source', 'tunerstop_historical'))->count();
```

### Test Reports

1. Go to CRM Admin Panel → Reports
2. Select date range from October 2020
3. Verify data appears in:
   - Sales by Brand
   - Sales by Model
   - Sales by Vehicle
   - Other reports

## Troubleshooting

### Connection Failed

```
❌ Cannot connect to source database
```

**Solution**: Verify `.env` credentials and ensure MySQL is running.

### Memory Issues

```
Allowed memory size exhausted
```

**Solution**: Use smaller batch size:
```bash
php artisan import:tunerstop-historical --batch-size=100
```

### Duplicate Orders

The import uses `updateOrCreate` with `external_order_id` to prevent duplicates. Re-running is safe.

### Missing Brand/Model in Reports

If brand/model appears as NULL in reports:
1. Check `order_items.product_snapshot` JSON field
2. The parsed name may not have matched the expected format
3. Historical items use parsed names, not linked products

## Files Created

```
database/scripts/import_tunerstop_historical_data.php  # Standalone script
app/Console/Commands/ImportTunerstopHistoricalData.php # Artisan command
config/database.php                                     # Added tunerstop_source connection
```

## Data Flow

```
tunerstop-data.sql
    ↓ (mysql import)
tunerstop_source database
    ↓ (php artisan import:tunerstop-historical)
reporting-crm database
    ↓
Reports Module
```

## Notes

1. **Historical Customer**: All orders are assigned to a placeholder "TunerStop Historical Orders" customer since original customer data isn't synced.

2. **External Source**: Imported orders have `external_source = 'tunerstop_historical'` to distinguish from live sync data.

3. **Order Numbers**: Prefixed with `TS-` to distinguish from CRM-generated orders.

4. **No Inventory Logs**: Historical orders don't create inventory logs (as per CRM design).

5. **No Expense Data**: Historical orders don't have expense fields populated - these are entered manually per invoice.
