# Reporting CRM - Complete System Architecture Documentation

## Master Index

**System:** Reporting CRM (Enterprise Management System)  
**Technology Stack:** Laravel 12 (LTS), PostgreSQL 15, Filament v3 Admin Panel, pqGrid Pro  
**Last Updated:** October 20, 2025  
**Documentation Generated:** October 20, 2025  
**Status:** ⚠️ Architecture Updated Based on Research Findings

---

## Executive Summary

The Reporting CRM is a comprehensive enterprise management system that serves as the central hub for:
- **Customer Management** (Unified B2B & B2C with Dealer Pricing)
- **Order Processing** (Unified Orders/Quotes/Invoices with document_type)
- **Product Catalog Management** (Snapshot-based approach, external sync)
- **Inventory Tracking** (Reference-only, external system as source of truth)
- **Consignment Management** (Full lifecycle: draft→sent→delivered→sold/returned)
- **Invoicing & Financial Tracking** (Payment recording, Expense tracking, Profit calculation)
- **Warranty Claims** (Cost tracking, SLA management)
- **Real-time Synchronization** from TunerStop Admin & Wholesale platforms
- **Wafeq Accounting Integration** (Payment & expense sync)

### 🎯 Key Architecture Principles

1. **Unified Orders Table** - Single `orders` table with `document_type` ENUM ('quote', 'invoice', 'order')
2. **Dealer Pricing Everywhere** - Pricing service activates in ALL modules when customer_type = 'dealer'
3. **Tax Inclusive/Exclusive** - Per-item tax handling (boolean on order_items, invoice_items, consignment_items)
4. **Financial Transaction Recording** - Record Payment, Record Expenses, Record Sale, Record Return
5. **Product Snapshots** - Store product data at time of order/quote (no deep relationships)
6. **Reuse Existing PDF Templates** - professional-invoice.blade.php, professional-consignment.blade.php

---

## System Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                    │
│          REPORTING CRM (Laravel 12 + Filament v3)                 │
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │  Customers   │  │   Products   │  │  Unified Orders Table  │ │
│  │  (Dealer     │  │ (Snapshot    │  │  document_type ENUM:   │ │
│  │   Pricing)   │  │  Approach)   │  │  - quote               │ │
│  │              │  │              │  │  - invoice             │ │
│  │              │  │              │  │  - order               │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │  Inventory   │  │ Consignment  │  │  Financial Tracking    │ │
│  │ (Reference-  │  │ (Record Sale │  │  - PaymentRecord       │ │
│  │  Only)       │  │  /Return)    │  │  - Expense Recording   │ │
│  │              │  │              │  │  - Profit Calculation  │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                    │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │  Warranty    │  │  AddOns &    │  │  Dealer Pricing        │ │
│  │  Claims      │  │ Accessories  │  │  Service (Global)      │ │
│  │ (Cost Track) │  │ (Tax Incl.)  │  │  - Model Priority      │ │
│  │              │  │              │  │  - Brand Priority      │ │
│  │              │  │              │  │  - Addon Category      │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
│                                                                    │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │             Wafeq Accounting Integration                    │  │
│  │  (Sync Payments, Expenses, Invoices)                       │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                    │
└──────────────┬──────────────────────────┬────────────────────────┘
               │                          │
     ┌─────────▼─────────┐      ┌────────▼────────┐
     │  TunerStop Admin  │      │ Wholesale Admin │
     │   (E-Commerce)    │      │  (B2B Platform) │
     │ Products + Orders │      │     Orders      │
     │  (Snapshot Sync)  │      │ (Snapshot Sync) │
     └───────────────────┘      └─────────────────┘
```

---

## Module Documentation

### Core Modules

#### 1. [Orders Module](ARCHITECTURE_ORDERS_MODULE.md)
**Status:** ⚠️ Updated - Unified Approach  
**Key Features:**
- **UNIFIED orders table** with `document_type` ENUM ('quote', 'invoice', 'order')
- Quote workflow: draft_quote → approved_quote → **convert to invoice**
- Tax inclusive/exclusive per item (boolean on order_items)
- **Dealer pricing integration** (auto-applies when customer_type = 'dealer')
- Payment status tracking
- Vehicle information capture
- Automatic sync from TunerStop & Wholesale (creates as 'quote' by default)
- Representative assignment
- **Financial transactions**: Record Payment, Record Expenses
- **Wafeq sync** for accounting integration

**Database Tables:**
- `orders` (UNIFIED: quotes + invoices + orders via document_type)
- `order_items` (with tax_inclusive boolean, product_snapshot JSON)
- `payment_records` (track partial/full payments)

**Key Files:**
- Model: `app/Models/Order.php` (convertQuoteToInvoice method)
- Model: `app/Models/PaymentRecord.php`
- Controller: `app/Http/Controllers/OrderController.php`
- Service: `app/Services/OrderSyncService.php` (snapshot-based)
- Service: `app/Services/DealerPricingService.php` (global pricing)
- Views: `resources/views/vendor/voyager/orders/`

---

#### 2. [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md)
**Status:** ⚠️ Updated - Dealer Pricing Everywhere  
**Key Features:**
- Unified customer system (Retail + Dealer)
- **customer_type ENUM**: 'retail', 'dealer', 'wholesale', 'corporate'
- **CRITICAL**: When customer_type = 'dealer', pricing service activates in ALL modules
- Address book system (multiple addresses per customer)
- **Dealer pricing rules** (Model Priority > Brand Priority > Addon Category)
- Representative assignment
- Business information (TRN, license, expiry)
- Soft delete with restore capability
- Customer payment history tracking

**Database Tables:**
- `customers` (main table with customer_type)
- `address_books` (addresses)
- `customer_model_pricing` (dealer discounts - HIGHEST priority)
- `customer_brand_pricing` (dealer discounts - MEDIUM priority)
- `customer_addon_category_pricing` (dealer addon discounts)

**Key Files:**
- Model: `app/Models/Customer.php`
- Controller: `app/Http/Controllers/Admin/CustomerController.php`
- Service: `app/Services/DealerPricingService.php` (shared across all modules)
- Views: `resources/views/admin/customers/`

---

#### 3. [Products Module](ARCHITECTURE_PRODUCTS_MODULE.md)
**Status:** ⚠️ Updated - Snapshot Approach  
**Key Features:**
- **Snapshot-based sync** from TunerStop Admin (NOT full catalog sync)
- Product snapshot stored in JSON on order_items (brand, model, finish, size, bolt_pattern, etc.)
- **Reference-only product data** (external system is source of truth)
- Brand/Model/Finish categorization for dealer pricing
- Multiple images (up to 10 per product)
- SEO optimization fields
- **Dealer pricing relationships** (customer_model_pricing, customer_brand_pricing)
- Search & discovery features

**Database Tables:**
- `products` (reference table - lightweight)
- `brands` (for dealer pricing)
- `models` (vehicle models for dealer pricing)
- `finishes`
- `product_images`

**Key Files:**
- Model: `app/Models/Product.php`
- Controller: `app/Http/Controllers/ProductsController.php`
- Sync Service: `app/Services/ProductSyncService.php` (snapshot-based, not full sync)
- Sync Controller: `app/Http/Controllers/ProductSyncController.php`

---

#### 4. [Product Variants Module](ARCHITECTURE_VARIANTS_MODULE.md)
**Status:** ⚠️ Updated - Snapshot + Dealer Pricing  
**Key Features:**
- SKU-based identification
- Detailed specifications (size, bolt pattern, offset, etc.)
- Multiple pricing fields (retail, wholesale, sale)
- **Dealer pricing applied via DealerPricingService**
- **tax_inclusive support** (boolean on variant level)
- **Product snapshot** captured when adding to order/quote
- Weight & load rating
- Out-of-stock scope (automatic filtering)
- Auto-append "mm" to offset

**Database Tables:**
- `product_variants` (with tax_inclusive boolean)

**Key Files:**
- Model: `app/Models/ProductVariant.php`
- Controller: `app/Http/Controllers/ProductVariantsController.php`

---

#### 5. [AddOns & AddOn Categories Module](ARCHITECTURE_ADDONS_MODULE.md)
**Status:** ⚠️ Updated - Dealer Pricing + Tax Inclusive  
**Key Features:**
- Category-specific attributes (6 categories)
- **Dealer pricing via customer_addon_category_pricing table**
- **tax_inclusive boolean** on addon items
- **Product snapshot** captured when adding to order/quote
- CSV import system
- Multi-warehouse inventory
- Restock notification system
- Dynamic required fields per category

**Categories:**
1. Wheel Accessories
2. Lug Nuts
3. Lug Bolts
4. Hub Rings
5. Spacers
6. TPMS

**Database Tables:**
- `add_ons` (with tax_inclusive boolean)
- `add_on_categories` (for dealer pricing relationships)
- `customer_addon_category_pricing` (dealer discounts)

**Key Files:**
- Model: `app/Models/AddOn.php`
- Model: `app/Models/AddOnCategory.php`
- Controller: `app/Http/Controllers/AddOnsController.php`
- Service: `app/Services/DealerPricingService.php` (addon category pricing)

---

#### 6. [Inventory & Warehouse Module](ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md)
**Status:** ⚠️ Updated - Reference-Only Approach  
**Key Features:**
- **Reference-only inventory** (external system as source of truth)
- Multi-warehouse support (for reference/reporting only)
- Geolocation-based fulfillment (Haversine formula)
- Distance calculation for nearest warehouse
- **Consignment return handling** (add items back to inventory)
- Product, variant, and addon inventory tracking
- ETA tracking
- Zero-quantity filtering

**Database Tables:**
- `product_inventories` (reference-only, not authoritative)
- `warehouses`

**Key Files:**
- Model: `app/Models/ProductInventory.php`
- Model: `app/Models/Warehouse.php`
- Controller: `app/Http/Controllers/ProductInventoryController.php`
- Service: `app/Services/SimplifiedInventoryService.php` (lookup-only)

---

#### 7. [Consignment Module](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md#part-1-consignment-module)
**Status:** ⚠️ Updated - Full Lifecycle + Financial Transactions  
**Key Features:**
- Send products to customers on trial/consignment
- Track items sent, sold, returned (quantity_sent, quantity_sold, quantity_returned)
- Status workflow (draft → sent → delivered → partially_sold → invoiced_in_full/returned_in_full)
- **Dealer pricing applies** when customer_type = 'dealer'
- **tax_inclusive per item** (boolean on consignment_items)
- **Record Sale** - Track items sold by customer, auto-create invoice
- **Record Return** - Track items returned, add back to inventory
- Hybrid system support (internal + external products)
- **Reuse professional-consignment.blade.php PDF template**

**Database Tables:**
- `consignments`
- `consignment_items` (with tax_inclusive boolean, quantity_sold, quantity_returned, actual_sale_price)
- `consignment_histories` (track sale/return events)

**Key Files:**
- Model: `app/Models/Consignment.php` (recordSale, recordReturn methods)
- Model: `app/Models/ConsignmentItem.php`
- Controller: `app/Http/Controllers/ConsignmentController.php`
- Service: `app/Services/DealerPricingService.php` (applies to consignments)
- PDF: `resources/views/pdfs/professional-consignment.blade.php`

---

#### 8. [Invoice Module](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md#part-2-invoice-module)
**Status:** ⚠️ Updated - Financial Transaction Recording  
**Key Features:**
- Generated from orders, quotes, or consignments
- **Linked to unified orders table** (converted from quotes)
- **Wafeq accounting integration** (sync payments & expenses)
- **Record Payment** - Track partial/full payments (PaymentRecord model)
- **Record Expenses** - 7 expense categories with auto profit calculation
- **Profit calculation** (gross_profit, profit_margin auto-calculated)
- **Dealer pricing applies** when customer_type = 'dealer'
- **tax_inclusive per item** (boolean on invoice_items)
- Payment history tracking
- Multiple payment methods (cash, card, bank_transfer, cheque)
- **Reuse professional-invoice.blade.php PDF template**

**Expense Categories:**
1. cost_of_goods
2. shipping_cost
3. duty_amount
4. delivery_fee
5. installation_cost
6. bank_fee
7. credit_card_fee

**Database Tables:**
- `invoices` (with expense fields, gross_profit, profit_margin)
- `invoice_items` (with tax_inclusive boolean)
- `payment_records` (NEW - replaces payments table)

**Key Files:**
- Model: `app/Models/Invoice.php` (recordPayment, recordExpenses, calculateProfit methods)
- Model: `app/Models/InvoiceItem.php`
- Model: `app/Models/PaymentRecord.php` (NEW - auto-generates payment numbers)
- Controller: `app/Http/Controllers/InvoiceController.php`
- Service: `app/Services/UnifiedQuoteInvoiceService.php`
- Service: `app/Services/WafeqService.php` (sync payments & expenses)
- Service: `app/Services/DealerPricingService.php` (applies to invoices)
- PDF: `resources/views/pdfs/professional-invoice.blade.php`

---

#### 9. [Warranty Claims Module](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md#part-3-warranty-claims-module)
**Status:** ⚠️ Updated - Dealer Pricing + Cost Tracking  
**Key Features:**
- Comprehensive claim management
- Status workflow (draft → submitted → under_review → approved/rejected → resolved → closed)
- SLA tracking (4-48 hours based on priority) with auto-escalation
- **Cost tracking** (replacement_cost, refund_amount, shipping_cost, total_claim_cost)
- **Dealer pricing applies to replacements** when customer_type = 'dealer'
- Root cause analysis
- Supplier cost recovery
- Customer satisfaction scoring
- Attachment support
- Auto-escalation on SLA breach

**Database Tables:**
- `warranty_claims` (with cost fields, sla_due_date, sla_breached)
- `warranty_claim_items`

**Key Files:**
- Model: `app/Models/WarrantyClaim.php`
- Model: `app/Models/WarrantyClaimItem.php`
- Controller: `app/Http/Controllers/WarrantyClaimController.php`
- Service: `app/Services/DealerPricingService.php` (applies to warranty replacements)

---

### Supporting Systems

#### 10. [Sync Processes Documentation](ARCHITECTURE_SYNC_PROCESSES.md)
**Status:** ⚠️ Updated - Snapshot-Based Sync + Wafeq Integration  
**Covers:**
- **Product snapshot sync** from TunerStop Admin (NOT full catalog sync)
- **Order sync** from TunerStop (creates as 'quote' with document_type)
- **Order sync** from Wholesale (creates as 'quote' with document_type)
- **Product snapshot** captured at time of order (JSON in order_items)
- **Wafeq accounting sync** (payments, expenses, invoices)
- Customer sync
- Data mapping services (Brand/Model/Finish) - for dealer pricing only
- Auto-create missing mappings
- Error handling & retry logic
- Queue management (queued jobs for Wafeq sync)
- Performance optimization

**Key Files:**
- Service: `app/Services/ProductSyncService.php` (snapshot-based)
- Service: `app/Services/OrderSyncService.php` (creates quotes with document_type)
- Service: `app/Services/WafeqService.php` (NEW - accounting sync)
- Service: `app/Services/BrandMappingService.php` (for dealer pricing)
- Service: `app/Services/ModelMappingService.php` (for dealer pricing)
- Service: `app/Services/FinishMappingService.php`
- Controller: `app/Http/Controllers/ProductSyncController.php`
- Controller: `app/Http/Controllers/BulkSyncController.php`
- Jobs: `app/Jobs/SyncPaymentToWafeq.php` (NEW)

---

## Database Architecture

### Total Tables: 40+

**Core Tables:**
- customers
- orders
- order_items
- order_item_quantities
- products
- product_variants
- product_inventories
- add_ons
- warehouses
- invoices
- consignments
- warranty_claims

**Supporting Tables:**
- address_books
- brands
- models
- finishes
- add_on_categories
- countries
- states
- users
- roles
- permissions

**Pricing Tables:**
- customer_brand_pricing
- customer_model_pricing
- customer_addon_category_pricing

**Mapping Tables:**
- brand_mappings
- model_mappings
- finish_mappings

**System Tables:**
- sync_operations
- activity_logs
- data_types (Voyager)

---

## API Endpoints

### Product Sync API
- `POST /api/sync/product` - Sync single product
- `POST /api/sync/product/queue` - Queue product sync
- `POST /api/sync/products/batch` - Batch sync
- `GET /api/sync/status` - Get sync status

### Order Sync API
- `POST /api/sync/order` - Sync single order
- `POST /api/sync/orders/batch` - Batch sync
- `GET /api/sync/orders/status` - Get sync status

### Customer Sync API
- `POST /api/sync/customer` - Sync customer

---

## Technology Stack

### Backend
- **Framework:** Laravel 9.x
- **PHP Version:** 8.1+
- **Database:** MySQL 8.0
- **Admin Panel:** Voyager 1.6
- **Search:** Spatie Laravel Searchable
- **Activity Log:** Spatie Laravel Activitylog
- **Soft Deletes:** Built-in Laravel

### Frontend
- **Admin UI:** Voyager (Vue.js components)
- **CSS Framework:** Bootstrap 4
- **Icons:** Font Awesome
- **JavaScript:** jQuery + Vue.js

### Infrastructure
- **Web Server:** Apache/Nginx
- **Queue:** Laravel Queue (Redis/Database)
- **Cache:** Redis/File
- **Storage:** AWS S3 (for images)
- **Email:** SMTP/SendGrid
- **Logging:** Laravel Log (Monolog)

---

## Key Business Processes

### 1. Product Management Flow
```
TunerStop Admin (Add Product)
    ↓
Auto-sync to Reporting CRM
    ↓
Map Brand/Model/Finish IDs
    ↓
Create/Update Product + Variants
    ↓
Sync Images
    ↓
Create Warehouse Inventory Records
    ↓
Product Available in Reporting
```

### 2. Retail Order Flow
```
Customer Places Order (TunerStop.com)
    ↓
Payment Processing
    ↓
Auto-sync to Reporting CRM
    ↓
Create/Update Customer
    ↓
Create Order Record
    ↓
Create Order Items
    ↓
Create Addresses
    ↓
Allocate from Nearest Warehouse
    ↓
Update Inventory
    ↓
Generate Invoice (optional)
    ↓
Send Confirmation Email
```

### 3. Wholesale Order Flow
```
Dealer Places Order (Wholesale Portal)
    ↓
Apply Dealer Pricing Rules
    ↓
Auto-sync to Reporting CRM
    ↓
Create/Update Dealer Customer
    ↓
Create Order Record
    ↓
Apply Discounts (Brand/Model/Category)
    ↓
Allocate Inventory
    ↓
Generate Invoice
    ↓
Sync to Wafeq Accounting
```

### 4. Consignment Flow
```
Create Consignment (Draft)
    ↓
Add Products/Variants
    ↓
Mark as Sent
    ↓
Ship to Customer
    ↓
Mark as Delivered
    ↓
Customer Trials Products
    ↓
Record Sales (Partially Sold)
    ↓
Generate Invoices for Sold Items
    ↓
Record Returns (Unsold Items)
    ↓
Update Inventory
    ↓
Mark as Invoiced/Returned
```

### 5. Warranty Claim Flow
```
Customer Reports Issue
    ↓
Create Warranty Claim
    ↓
Assign to Representative
    ↓
Set Priority & SLA
    ↓
Under Review
    ↓
Root Cause Analysis
    ↓
Decision: Approve/Reject
    ↓
If Approved:
    - Record Resolution (Replacement/Refund/Repair)
    - Track All Costs
    - Attempt Supplier Cost Recovery
    ↓
Customer Feedback
    ↓
Close Claim
    ↓
Generate Analytics
```

---

## Data Synchronization Matrix

| Source | Target | Trigger | Frequency | Method |
|--------|--------|---------|-----------|--------|
| TunerStop Admin | Reporting | Product CRUD | Real-time | API POST |
| TunerStop Admin | Reporting | Order Placed | Real-time | API POST |
| Wholesale Admin | Reporting | Order Placed | Real-time | API POST |
| Reporting | Wafeq | Invoice Created | Manual/Auto | API POST |
| Reporting | TunerStop | Inventory Update | Future | API POST |

---

## Performance Metrics

### Current System Load
- **Total Products:** ~15,000
- **Total Variants:** ~60,000
- **Total Customers:** ~5,000
- **Monthly Orders:** ~2,000
- **Warehouses:** 5-10
- **Daily API Calls:** ~500

### Response Times (Target)
- **Product Search:** < 500ms
- **Order Creation:** < 2s
- **Sync Operations:** < 5s
- **Inventory Query:** < 300ms
- **Dashboard Load:** < 1s

---

## Security Features

### Authentication
- Multi-level user roles (Admin, Sales, Warehouse, etc.)
- API Bearer token authentication
- Session management
- Two-factor authentication (optional)

### Authorization
- Role-based access control (RBAC)
- Permission-based feature access
- Customer data segregation
- Admin-only sensitive operations

### Data Protection
- Soft deletes (data preservation)
- Activity logging
- GDPR compliance ready
- Encrypted sensitive data
- Backup & restore procedures

---

## Deployment Architecture

### Production Environment
```
┌──────────────────────┐
│   Load Balancer      │
└──────────┬───────────┘
           │
     ┌─────┴─────┐
     │           │
┌────▼────┐ ┌───▼─────┐
│  Web 1  │ │  Web 2  │
└────┬────┘ └───┬─────┘
     │          │
     └────┬─────┘
          │
     ┌────▼──────┐
     │  Database │
     │  (Master) │
     └────┬──────┘
          │
     ┌────▼──────┐
     │  Database │
     │  (Slave)  │
     └───────────┘

┌─────────────┐  ┌─────────────┐
│   Redis     │  │    S3       │
│   (Cache)   │  │  (Storage)  │
└─────────────┘  └─────────────┘

┌─────────────┐  ┌─────────────┐
│ Queue Worker│  │   Cron      │
│  (Jobs)     │  │  (Schedule) │
└─────────────┘  └─────────────┘
```

---

## Maintenance & Monitoring

### Scheduled Tasks
- **Daily:** Sync validation, backup
- **Hourly:** Queue processing, inventory updates
- **Weekly:** Analytics generation, cleanup
- **Monthly:** Performance reports

### Monitoring Tools
- Laravel Telescope (development)
- Log monitoring (production)
- Database query analysis
- API response time tracking
- Error rate monitoring

---

## Future Roadmap

### Phase 1 (Q1 2026)
- [ ] Real-time inventory sync back to TunerStop
- [ ] Advanced analytics dashboard
- [ ] Mobile app for warehouse management

### Phase 2 (Q2 2026)
- [ ] AI-powered demand forecasting
- [ ] Automated reordering system
- [ ] Enhanced reporting capabilities

### Phase 3 (Q3 2026)
- [ ] Multi-currency support
- [ ] International shipping integration
- [ ] Advanced CRM features

---

## Contact & Support

**System Administrator:** [Your Name]  
**Email:** [admin@reporting.com]  
**Documentation Repository:** [Git Repo URL]  
**Support Portal:** [Support URL]

---

## Document Revision History

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | 2025-10-20 | Initial comprehensive documentation | AI Assistant |

---

## Quick Reference Links

- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md)
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md)
- [Products Module](ARCHITECTURE_PRODUCTS_MODULE.md)
- [Variants Module](ARCHITECTURE_VARIANTS_MODULE.md)
- [AddOns Module](ARCHITECTURE_ADDONS_MODULE.md)
- [Inventory & Warehouse Module](ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md)
- [Consignment, Invoice & Warranty Claims](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md)
- [Sync Processes](ARCHITECTURE_SYNC_PROCESSES.md)

---

**END OF MASTER INDEX**
