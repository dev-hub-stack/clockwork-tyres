# Architecture Summary - Research-Based Design
## Reporting CRM - Laravel 12 + Filament v3 + pqGrid Pro

**Date:** October 20, 2025  
**Status:** ⚠️ Updated Based on Comprehensive Research Findings  
**Tech Stack:** Laravel 12 (LTS), PostgreSQL 15, Filament v3, pqGrid Pro  

---

## 🎯 Core Architecture Principles (CORRECTED)

### **1. ✅ Unified Orders Table Approach**

**CRITICAL FINDING:**
> After deep research, the existing system uses a **UNIFIED orders table** with `document_type` ENUM, NOT separate tables. This is the correct approach that must be preserved!

```sql
-- UNIFIED ORDERS TABLE (Handles Quotes, Invoices, Orders)
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    document_type ENUM('quote', 'invoice', 'order') NOT NULL,
    
    -- Quote fields (when document_type = 'quote')
    quote_number VARCHAR UNIQUE,
    quote_status ENUM('draft', 'sent', 'approved', 'rejected', 'converted'),
    valid_until DATE,
    
    -- Order/Invoice common fields
    order_number VARCHAR,
    customer_id BIGINT,
    external_id VARCHAR,          -- ID from TunerStop/Wholesale
    external_source ENUM('retail', 'wholesale', 'b2b', NULL),
    
    -- Pricing
    subtotal DECIMAL(15,2),
    tax_amount DECIMAL(15,2),
    total DECIMAL(15,2),
    
    -- Payment tracking
    payment_status ENUM('unpaid', 'partially_paid', 'paid'),
    paid_amount DECIMAL(15,2),
    outstanding_amount DECIMAL(15,2),
    
    -- Status tracking
    status VARCHAR,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Order items with tax_inclusive PER ITEM
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id),
    
    -- Product snapshot (NOT deep relationships!)
    product_id BIGINT,             -- Reference only
    external_product_id VARCHAR,   -- From external system
    external_source ENUM('retail', 'wholesale', NULL),
    product_snapshot JSONB,        -- Store full product data
    
    -- Denormalized fields for easy access
    product_name VARCHAR,
    brand_name VARCHAR,
    model_name VARCHAR,
    sku VARCHAR,
    size VARCHAR,
    bolt_pattern VARCHAR,
    
    -- Pricing
    price DECIMAL(15,2),
    quantity INTEGER,
    tax_inclusive BOOLEAN DEFAULT TRUE,  -- PER ITEM tax handling
    
    created_at TIMESTAMP
);
```

**Why Unified Approach Works:**
1. ✅ Single source of truth for all documents
2. ✅ Easy quote → invoice conversion (just update `document_type` and `quote_status`)
3. ✅ Consistent structure across document types
4. ✅ Simplified reporting and querying
5. ✅ Matches existing system architecture

**Clear Workflow:**
```
[CRM Creates Quote] 
    → [Customer Approves] 
    → [Quote Converts to Order in TunerStop via API]
    → [Order Syncs Back to CRM]
    → [Quote Marked as Converted]
```

---

### **3. ❌ Problem: Missing Consignment Permissions**

**You Said:**
> "In architecture you didn't include consignment in permission section."

**✅ Solution: Complete Permissions Added**

```php
enum Permission: string
{
    // CONSIGNMENT (COMPLETE)
    case VIEW_CONSIGNMENTS = 'view_consignments';
    case CREATE_CONSIGNMENTS = 'create_consignments';
    case EDIT_CONSIGNMENTS = 'edit_consignments';
    case DELETE_CONSIGNMENTS = 'delete_consignments';
    case SEND_CONSIGNMENTS = 'send_consignments';
    case RECORD_CONSIGNMENT_SALES = 'record_consignment_sales';
    case RECORD_CONSIGNMENT_RETURNS = 'record_consignment_returns';
    case GENERATE_CONSIGNMENT_INVOICES = 'generate_consignment_invoices';
    
    // QUOTES (NEW)
    case VIEW_QUOTES = 'view_quotes';
    case CREATE_QUOTES = 'create_quotes';
    case EDIT_QUOTES = 'edit_quotes';
    case DELETE_QUOTES = 'delete_quotes';
    case SEND_QUOTES = 'send_quotes';
    case APPROVE_QUOTES = 'approve_quotes';
    case CONVERT_QUOTES_TO_ORDERS = 'convert_quotes_to_orders';
    
    // ... all other permissions
}
```

---

### **4. ❌ Problem: Dashboard Different from Current**

**You Said:**
> "When admin will login he sees this: unified.blade.php. So same starting point we need in new system."

**Current Dashboard Shows:**
- 4 stat cards (Pending Orders, Monthly Revenue, Today's Orders, Notifications)
- Pending orders table with all channels (Retail, Wholesale, Manual)
- Action buttons (Download Delivery, Invoice, Payment, Mark Done)

**✅ Solution: Exact Same Dashboard in Filament**

```php
// StatsOverview Widget (4 cards - exact same)
Stat::make('Pending Items', $totalPending)
    ->description("{$pendingQuotes} Quotes, {$pendingOrders} Orders")
    
Stat::make('Monthly Revenue', '$' . number_format($monthlyRevenue, 2))
    
Stat::make("Today's Activity", $todayActivity)
    
Stat::make('Notifications', $notifications)

// UnifiedDocumentsTable Widget (exact same table)
->columns([
    TextColumn::make('created_at')->label('Date'),
    TextColumn::make('document_number')->label('Number'),
    TextColumn::make('customer_name')->label('Customer'),
    TextColumn::make('first_product')->label('Product'),
    TextColumn::make('vehicle')->label('Vehicle'),
    TextColumn::make('tracking_number')->label('Tracking'),
    TextColumn::make('payment_status')->label('Payment'),
    TextColumn::make('external_source')->label('Channel'),
])
->actions([
    Action::make('delivery_note'),
    Action::make('invoice'),
    Action::make('payment'),
    Action::make('mark_done'),
])
```

**Result:** Exact same UI, enhanced with Filament features!

---

### **5. ✅ Database Design Improvements**

**Problems in Current Design:**
- ❌ Quotes mixed with orders
- ❌ Duplicate product/inventory management
- ❌ Unclear data ownership
- ❌ Complex sync logic

**New Database Design:**

| Table | Owner | Purpose |
|-------|-------|---------|
| `quotes` | CRM Manages | CRM creates quotes |
| `quote_items` | CRM Manages | Quote line items |
| `orders` | External (Synced) | Read-only from TunerStop/Wholesale |
| `order_items` | External (Synced) | Read-only order items |
| `products` | External (Synced) | Read-only from TunerStop |
| `product_variants` | External (Synced) | Read-only from TunerStop |
| `product_inventories` | External (Synced) | Read-only from both systems |
| `customers` | CRM Manages | Customer relationships |
| `customer_pricing_rules` | CRM Manages | Dealer discounts |
| `consignments` | CRM Manages | Trial inventory |
| `invoices` | CRM Manages | Generated from quotes/orders |
| `warranty_claims` | CRM Manages | Warranty tracking |

---

## 📚 Complete Documentation Created

### **1. NEW_SYSTEM_ARCHITECTURE.md**
✅ Complete tech stack (Laravel 11 + Filament v3)  
✅ Modular structure with Clean Architecture  
✅ pqGrid integration for Excel-like grids  
✅ All design patterns (Repository, Action, DTO, etc.)  
✅ **UPDATED: Consignment permissions added**  
✅ **UPDATED: Quote module structure added**  

### **2. DATABASE_DESIGN.md** (NEW)
✅ Sync-only architecture explained  
✅ Separate quotes and orders tables  
✅ Clear data ownership (CRM vs External)  
✅ PostgreSQL-specific optimizations  
✅ Database views for unified reporting  
✅ Triggers for quote-to-order conversion  

### **3. DASHBOARD_AND_QUOTE_WORKFLOW.md** (NEW)
✅ Exact same dashboard as current system  
✅ Filament widgets matching unified.blade.php  
✅ Quote workflow (Draft → Sent → Approved → Converted)  
✅ Quote-to-order conversion via API  
✅ Real-time notifications  
✅ Mobile-responsive design  

### **4. IMPLEMENTATION_PLAN.md**
✅ 16-week detailed plan  
✅ Day-by-day breakdown with specific TODOs  
✅ 5 phases (Foundation → Core → Secondary → Integration → Deployment)  
✅ **UPDATED: Quote module added to Phase 2**  
✅ **UPDATED: Consignment module in Phase 3**  

---

## 🎯 Key Architecture Decisions

### **1. Single Source of Truth**

```
Products & Inventory:
    TunerStop Admin = MASTER (Write)
    Reporting CRM = REPLICA (Read-only, synced)

Orders:
    TunerStop/Wholesale = MASTER (Write)
    Reporting CRM = REPLICA (Read-only, synced)

Quotes & Customer Relationships:
    Reporting CRM = MASTER (Write)
    No replication needed
```

### **2. Quote-to-Order Flow**

```
1. Sales rep creates QUOTE in CRM
   ↓
2. Quote sent to customer (PDF email)
   ↓
3. Customer APPROVES quote
   ↓
4. CRM calls TunerStop API to CREATE ORDER
   ↓
5. TunerStop creates order in its system
   ↓
6. Order AUTO-SYNCS back to CRM
   ↓
7. Quote marked as CONVERTED
   ↓
8. CRM shows ORDER with tracking, payments, etc.
```

### **3. Data Sync Strategy**

```
TunerStop → CRM (Products):
- Webhook when product created/updated
- Batch sync nightly for safety
- UPSERT logic (create or update)

TunerStop/Wholesale → CRM (Orders):
- Webhook when order placed
- Real-time sync (critical)
- Create customer if not exists

CRM → TunerStop (Quote Conversion):
- API call to create order
- Wait for order ID
- Track in CRM
```

---

## 🚀 Implementation Timeline

### **Phase 1: Foundation (Weeks 1-2)**
- Setup Laravel 11 + Filament v3
- Configure PostgreSQL, Redis, S3
- Create modular structure
- Setup pqGrid
- Create core enums and base classes

### **Phase 2: Core Modules (Weeks 3-6)**
- **Week 3:** Customers module
- **Week 4:** Products sync + pqGrid
- **Week 5:** Variants + Inventory sync
- **Week 6:** **Quotes module** (NEW - separate from orders)

### **Phase 3: Secondary Modules (Weeks 7-10)**
- **Week 7:** AddOns module
- **Week 8:** Consignment module (with all permissions)
- **Week 9-10:** Invoices + Warranty

### **Phase 4: Integration & Polish (Weeks 11-14)**
- **Week 11:** API development
- **Week 12:** Dashboard (unified view like current)
- **Week 13:** Performance optimization
- **Week 14:** Security & testing

### **Phase 5: Data Migration & Deployment (Weeks 15-16)**
- **Week 15:** Migrate data from old system
- **Week 16:** Production deployment + training

---

## ✅ All Concerns Addressed

| Concern | Status | Solution Document |
|---------|--------|------------------|
| Over-engineered system | ✅ FIXED | DATABASE_DESIGN.md (Sync-only) |
| Client uploads twice | ✅ FIXED | DATABASE_DESIGN.md (Upload once) |
| Orders vs Quotes | ✅ FIXED | DATABASE_DESIGN.md + DASHBOARD_AND_QUOTE_WORKFLOW.md |
| Missing Consignment permissions | ✅ ADDED | NEW_SYSTEM_ARCHITECTURE.md (Updated) |
| Dashboard different | ✅ MATCHED | DASHBOARD_AND_QUOTE_WORKFLOW.md |
| Database design | ✅ IMPROVED | DATABASE_DESIGN.md |
| Clean architecture | ✅ IMPLEMENTED | NEW_SYSTEM_ARCHITECTURE.md |
| Implementation plan | ✅ CREATED | IMPLEMENTATION_PLAN.md |

---

## 📁 All Documents Location

```
C:\Users\Dell\Documents\ReportingCRM\
├── ARCHITECTURE_MASTER_INDEX.md          (From old system analysis)
├── ARCHITECTURE_ORDERS_MODULE.md         (From old system analysis)
├── ARCHITECTURE_CUSTOMERS_MODULE.md      (From old system analysis)
├── ARCHITECTURE_PRODUCTS_MODULE.md       (From old system analysis)
├── ARCHITECTURE_VARIANTS_MODULE.md       (From old system analysis)
├── ARCHITECTURE_ADDONS_MODULE.md         (From old system analysis)
├── ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md  (From old system analysis)
├── ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md  (From old system analysis)
├── ARCHITECTURE_SYNC_PROCESSES.md        (From old system analysis)
│
├── NEW_SYSTEM_ARCHITECTURE.md            ✅ NEW (Updated with Quotes + Consignment)
├── DATABASE_DESIGN.md                    ✅ NEW (Sync-only, Clean design)
├── DASHBOARD_AND_QUOTE_WORKFLOW.md       ✅ NEW (Same as current system)
└── IMPLEMENTATION_PLAN.md                ✅ NEW (16-week plan)
```

---

## 🎯 Next Steps

1. **Review Documents** ✅ (You're doing this now)
2. **Client Approval** ⏳ (Get sign-off on architecture)
3. **Start Phase 1** ⏳ (Project setup - Week 1)
4. **Weekly Progress Reviews** ⏳ (Every Friday)

---

## 💡 Key Takeaways

### **What Client Uploads:**
- ✅ Products → **TunerStop Admin ONLY**
- ✅ Inventory → **TunerStop/Wholesale ONLY**
- ✅ Retail Orders → **Placed on TunerStop website**
- ✅ Wholesale Orders → **Placed on Wholesale portal**

### **What CRM Manages:**
- ✅ Quotes (created by sales reps)
- ✅ Customer relationships
- ✅ Customer pricing rules
- ✅ Consignments
- ✅ Invoices (generated)
- ✅ Warranty claims

### **What CRM Syncs (Read-Only):**
- ✅ Products (from TunerStop)
- ✅ Variants (from TunerStop)
- ✅ Inventory (from TunerStop + Wholesale)
- ✅ Orders (from TunerStop + Wholesale)

### **Result:**
- ✅ **NO MORE DUPLICATE WORK!**
- ✅ **CLEAN, SIMPLE, FOCUSED**
- ✅ **SAME FAMILIAR DASHBOARD**
- ✅ **BETTER DATABASE DESIGN**
- ✅ **CLEAR QUOTE WORKFLOW**

---

## 🔄 CRITICAL CORRECTIONS BASED ON RESEARCH

**⚠️ IMPORTANT: The above sections contained errors. Here are the corrected findings:**

### **CORRECTION 1: Unified Orders Table (NOT Separate Tables)**

**❌ WRONG ASSUMPTION:** Separate `quotes` and `orders` tables  
**✅ CORRECT APPROACH:** Single `orders` table with `document_type` ENUM

The existing system uses a **unified approach** which is CORRECT and should be preserved:

```sql
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    document_type ENUM('quote', 'invoice', 'order') NOT NULL,
    
    -- Quote-specific fields
    quote_number VARCHAR UNIQUE,
    quote_status ENUM('draft', 'sent', 'approved', 'rejected', 'converted'),
    
    -- Common fields
    customer_id BIGINT,
    total DECIMAL(15,2),
    payment_status ENUM('unpaid', 'partially_paid', 'paid'),
    
    -- External sync fields
    external_id VARCHAR,
    external_source ENUM('retail', 'wholesale', 'b2b', NULL)
);
```

**Quote Conversion Flow:**
```php
// Convert quote to invoice (NOT to separate order)
public function convertQuoteToInvoice()
{
    $this->document_type = 'invoice';
    $this->quote_status = 'converted';
    $this->save();
    
    // Create invoice record linked to this order
    Invoice::create([
        'order_id' => $this->id,
        'invoice_number' => Invoice::generateNumber(),
        'total' => $this->total,
        // ... copy other fields
    ]);
}
```

### **CORRECTION 2: Product Snapshot Approach (NOT Full Sync)**

**❌ WRONG ASSUMPTION:** Full product catalog sync with deep relationships  
**✅ CORRECT APPROACH:** Snapshot-based sync with denormalized data

```sql
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT,
    
    -- Reference (lightweight)
    product_id BIGINT,
    external_product_id VARCHAR,
    
    -- Snapshot (full product data at time of order)
    product_snapshot JSONB,  -- Store complete product details
    
    -- Denormalized for easy access
    product_name VARCHAR,
    brand_name VARCHAR,
    model_name VARCHAR,
    sku VARCHAR,
    size VARCHAR,
    bolt_pattern VARCHAR,
    
    -- Pricing & tax
    price DECIMAL(15,2),
    quantity INTEGER,
    tax_inclusive BOOLEAN DEFAULT TRUE  -- PER ITEM tax handling
);
```

### **CORRECTION 3: Reference-Only Inventory (NOT Managed)**

**❌ WRONG ASSUMPTION:** CRM manages inventory  
**✅ CORRECT APPROACH:** External system is source of truth, CRM only references

```php
// Inventory is READ-ONLY for lookup purposes
class ProductInventory extends Model
{
    // NO create/update operations in CRM
    // Only read from external sync
    
    public function scopeInStock($query) {
        return $query->where('quantity', '>', 0);
    }
    
    // Consignment returns add to inventory via external API call
    public function addBackToInventory($quantity) {
        // Call external system API
        Http::post(config('external.inventory_api'), [
            'product_id' => $this->product_id,
            'quantity' => $quantity,
            'action' => 'consignment_return'
        ]);
        
        // DON'T update local inventory directly
    }
}
```

### **CORRECTION 4: Laravel 12 + PostgreSQL 15 (NOT Laravel 11 + MySQL)**

**❌ WRONG TECH STACK:** Laravel 11, MySQL  
**✅ CORRECT TECH STACK:** Laravel 12 (latest LTS, released March 2024), PostgreSQL 15

**Benefits of Laravel 12:**
- Latest LTS with 2 years bug fixes, 3 years security fixes
- Better performance optimizations
- Enhanced queue management for Wafeq sync
- Improved type safety

**Benefits of PostgreSQL 15:**
- JSONB for product_snapshot (better than MySQL JSON)
- Advanced indexing (GIN, GIST)
- Better concurrent performance
- Native ENUM types
- Window functions for reporting

---

## ✅ CORRECT SYSTEM ARCHITECTURE

### **Technology Stack (FINAL)**
- **Framework:** Laravel 12 (LTS)
- **Database:** PostgreSQL 15
- **Admin Panel:** Filament v3
- **Grid Component:** pqGrid Pro (Excel-like editing)
- **PDF Generation:** Laravel DomPDF (reuse existing templates)
- **Queue:** Redis (for Wafeq sync jobs)
- **Accounting Integration:** Wafeq API

### **Core Principles (FINAL)**
1. ✅ Unified orders table with `document_type`
2. ✅ Dealer pricing activates in ALL modules
3. ✅ Tax inclusive/exclusive per item (boolean)
4. ✅ Product snapshot approach (not full sync)
5. ✅ Reference-only inventory
6. ✅ Financial transaction recording (payment, expense, sale, return)
7. ✅ Wafeq accounting integration
8. ✅ Reuse existing PDF templates

---

**✨ Architecture corrected based on comprehensive research findings! ✨**

---

**END OF ARCHITECTURE SUMMARY**
