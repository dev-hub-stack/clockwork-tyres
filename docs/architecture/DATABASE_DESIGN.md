# Database Design - Reporting CRM v2.0
## Clean, Sync-First Architecture

**Date:** October 20, 2025  
**Philosophy:** Single Source of Truth - CRM Only Manages Quotes & Relationships  

---

## 🎯 Core Principle: SYNC-ONLY ARCHITECTURE

### **Problem with Current System:**
❌ CRM maintains its own products  
❌ CRM maintains its own inventory  
❌ CRM duplicates data from TunerStop & Wholesale  
❌ Over-engineered - client uploads same data twice  

### **New Architecture Solution:**
✅ **TunerStop Admin** = Master for Products, Inventory, Retail Orders  
✅ **Wholesale Admin** = Master for Wholesale Orders, Inventory  
✅ **Reporting CRM** = Master ONLY for Quotes, Customers, Relationships  
✅ **CRM syncs data** but doesn't manage it directly  
✅ **Client uploads once** in TunerStop/Wholesale, CRM auto-syncs  

---

## 📊 Database Schema Design

### **Entity Relationship Diagram**

```
┌─────────────────────────────────────────────────────────────┐
│              EXTERNAL SYSTEMS (MASTER DATA)                 │
├─────────────────────────────────────────────────────────────┤
│  TunerStop Admin:                                           │
│  - Products (Master)                                        │
│  - Product Variants (Master)                                │
│  - Product Images (Master)                                  │
│  - Inventory (Master)                                       │
│  - Retail Orders (Master)                                   │
│                                                              │
│  Wholesale Admin:                                           │
│  - Wholesale Orders (Master)                                │
│  - Wholesale Inventory (Master)                             │
└─────────────────────────────────────────────────────────────┘
                           ↓ SYNC ↓
┌─────────────────────────────────────────────────────────────┐
│          REPORTING CRM (READ-ONLY SYNCED DATA)              │
├─────────────────────────────────────────────────────────────┤
│  Synced (Read-Only):                                        │
│  - products (synced from TunerStop)                         │
│  - product_variants (synced from TunerStop)                 │
│  - product_inventories (synced from TunerStop + Wholesale)  │
│  - orders (synced from TunerStop + Wholesale)               │
│                                                              │
│  CRM-Managed (Write):                                       │
│  - quotes (CRM creates these)                               │
│  - customers (CRM manages relationships)                    │
│  - consignments (CRM-specific)                              │
│  - invoices (Generated from quotes/orders)                  │
│  - warranty_claims (CRM-specific)                           │
│  - customer_pricing_rules (CRM-specific)                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Table Definitions

### **1. QUOTES (CRM-Managed)**

**Purpose:** CRM creates quotes for customers, which can be converted to orders

```sql
CREATE TABLE quotes (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Customer
    customer_id BIGINT REFERENCES customers(id) ON DELETE RESTRICT,
    
    -- Quote Details
    document_type VARCHAR(20) DEFAULT 'quote' CHECK (document_type IN ('quote', 'proforma')),
    quote_status VARCHAR(20) DEFAULT 'draft' CHECK (quote_status IN (
        'draft', 'sent', 'approved', 'rejected', 'converted', 'expired'
    )),
    
    -- Validity
    valid_until DATE NOT NULL,
    expires_at TIMESTAMP GENERATED ALWAYS AS (valid_until + INTERVAL '23 hours 59 minutes') STORED,
    
    -- Conversion
    converted_to_order_id BIGINT REFERENCES orders(id) ON DELETE SET NULL,
    converted_at TIMESTAMP,
    
    -- Financial
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    
    -- Vehicle Info (for quote context)
    vehicle_year INT,
    vehicle_make VARCHAR(100),
    vehicle_model VARCHAR(100),
    vehicle_trim VARCHAR(100),
    
    -- Notes
    internal_notes TEXT,
    customer_notes TEXT,
    terms_and_conditions TEXT,
    
    -- Tracking
    created_by BIGINT REFERENCES users(id),
    approved_by BIGINT REFERENCES users(id),
    representative_id BIGINT REFERENCES users(id),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    sent_at TIMESTAMP,
    approved_at TIMESTAMP,
    
    -- Soft Delete
    deleted_at TIMESTAMP
);

-- Indexes
CREATE INDEX idx_quotes_customer ON quotes(customer_id);
CREATE INDEX idx_quotes_status ON quotes(quote_status);
CREATE INDEX idx_quotes_representative ON quotes(representative_id);
CREATE INDEX idx_quotes_valid_until ON quotes(valid_until);
CREATE INDEX idx_quotes_created_at ON quotes(created_at DESC);

-- Trigger for quote number generation
CREATE OR REPLACE FUNCTION generate_quote_number()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.quote_number IS NULL THEN
        NEW.quote_number := 'Q' || TO_CHAR(NOW(), 'YYYY') || LPAD(NEW.id::TEXT, 6, '0');
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_generate_quote_number
BEFORE INSERT ON quotes
FOR EACH ROW
EXECUTE FUNCTION generate_quote_number();
```

### **2. QUOTE ITEMS (CRM-Managed)**

```sql
CREATE TABLE quote_items (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    quote_id BIGINT NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,
    
    -- Product Reference (from synced data)
    product_id BIGINT REFERENCES products(id) ON DELETE RESTRICT,
    product_variant_id BIGINT REFERENCES product_variants(id) ON DELETE RESTRICT,
    addon_id BIGINT REFERENCES add_ons(id) ON DELETE RESTRICT,
    
    -- Item Details (snapshot at quote time)
    item_type VARCHAR(20) CHECK (item_type IN ('product', 'variant', 'addon', 'custom')),
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100),
    description TEXT,
    
    -- Specifications (for reference)
    specifications JSONB,
    
    -- Pricing
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    line_total DECIMAL(10,2) GENERATED ALWAYS AS (
        (quantity * unit_price) - discount_amount
    ) STORED,
    
    -- Notes
    notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_quote_items_quote ON quote_items(quote_id);
CREATE INDEX idx_quote_items_product ON quote_items(product_id);
```

### **3. CUSTOMERS (CRM-Managed with Pricing Rules)**

```sql
CREATE TABLE customers (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Basic Info
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    
    -- Type
    customer_type VARCHAR(20) DEFAULT 'retail' CHECK (customer_type IN ('retail', 'dealer', 'b2b')),
    
    -- Business Info (for dealers/b2b)
    business_name VARCHAR(255),
    trn VARCHAR(100), -- Tax Registration Number
    trade_license VARCHAR(100),
    license_expiry_date DATE,
    
    -- Representative
    representative_id BIGINT REFERENCES users(id),
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    credit_limit DECIMAL(12,2) DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP -- Soft delete
);

CREATE INDEX idx_customers_type ON customers(customer_type);
CREATE INDEX idx_customers_representative ON customers(representative_id);
CREATE INDEX idx_customers_email ON customers(email);
```

### **4. ORDERS (Synced from TunerStop + Wholesale - READ ONLY)**

```sql
CREATE TABLE orders (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- External Reference
    external_id VARCHAR(100) NOT NULL, -- ID from TunerStop/Wholesale
    external_source VARCHAR(20) NOT NULL CHECK (external_source IN ('retail', 'wholesale', 'b2b')),
    
    -- Order Details
    order_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Customer (linked or created during sync)
    customer_id BIGINT REFERENCES customers(id),
    external_customer_id VARCHAR(100), -- Customer ID from source system
    
    -- Order Type
    order_type VARCHAR(20) CHECK (order_type IN ('retail', 'wholesale', 'b2b')),
    
    -- Status
    order_status VARCHAR(30) NOT NULL CHECK (order_status IN (
        'pending', 'processing', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled'
    )),
    payment_status VARCHAR(20) CHECK (payment_status IN (
        'pending', 'partial', 'paid', 'refunded'
    )),
    
    -- Financial
    subtotal DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    shipping_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    outstanding_amount DECIMAL(12,2) GENERATED ALWAYS AS (
        total_amount - paid_amount
    ) STORED,
    
    -- Vehicle Info
    vehicle_year INT,
    vehicle_make VARCHAR(100),
    vehicle_model VARCHAR(100),
    vehicle_trim VARCHAR(100),
    
    -- Shipping
    tracking_number VARCHAR(100),
    shipping_carrier VARCHAR(100),
    shipping_method VARCHAR(100),
    
    -- Related Quote (if converted from quote)
    quote_id BIGINT REFERENCES quotes(id),
    
    -- Representative
    representative_id BIGINT REFERENCES users(id),
    
    -- Sync Info
    last_synced_at TIMESTAMP,
    sync_status VARCHAR(20) DEFAULT 'synced' CHECK (sync_status IN ('synced', 'pending', 'failed')),
    sync_error TEXT,
    
    -- Notes
    notes TEXT,
    
    -- Timestamps (from source system)
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    
    -- Unique constraint on external reference
    CONSTRAINT uq_orders_external UNIQUE (external_source, external_id)
);

-- Indexes
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(order_status);
CREATE INDEX idx_orders_external ON orders(external_source, external_id);
CREATE INDEX idx_orders_quote ON orders(quote_id);
CREATE INDEX idx_orders_created ON orders(created_at DESC);
```

### **5. ORDER ITEMS (Synced - READ ONLY)**

```sql
CREATE TABLE order_items (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Order Reference
    order_id BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    
    -- External Reference
    external_id VARCHAR(100),
    
    -- Product Reference (linked to synced products)
    product_id BIGINT REFERENCES products(id),
    product_variant_id BIGINT REFERENCES product_variants(id),
    addon_id BIGINT REFERENCES add_ons(id),
    
    -- Item Details (snapshot from source)
    item_type VARCHAR(20),
    item_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100),
    description TEXT,
    
    -- Quantities
    quantity INT NOT NULL DEFAULT 1,
    
    -- Pricing
    unit_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    line_total DECIMAL(10,2) NOT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
```

### **6. PRODUCTS (Synced from TunerStop - READ ONLY)**

```sql
CREATE TABLE products (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- External Reference
    external_id VARCHAR(100) NOT NULL UNIQUE, -- ID from TunerStop
    
    -- Product Info
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    sku VARCHAR(100),
    description TEXT,
    
    -- Categorization
    brand_id BIGINT REFERENCES brands(id),
    model_id BIGINT REFERENCES models(id), -- Vehicle model
    finish_id BIGINT REFERENCES finishes(id),
    
    -- Pricing (from TunerStop)
    retail_price DECIMAL(10,2),
    wholesale_price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- SEO
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    
    -- Sync Info
    last_synced_at TIMESTAMP,
    sync_status VARCHAR(20) DEFAULT 'synced',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    -- Search
    search_vector tsvector GENERATED ALWAYS AS (
        to_tsvector('english', coalesce(name, '') || ' ' || coalesce(sku, '') || ' ' || coalesce(description, ''))
    ) STORED
);

CREATE INDEX idx_products_external ON products(external_id);
CREATE INDEX idx_products_brand ON products(brand_id);
CREATE INDEX idx_products_search ON products USING GIN(search_vector);
CREATE INDEX idx_products_active ON products(is_active) WHERE is_active = TRUE;
```

### **7. PRODUCT VARIANTS (Synced from TunerStop - READ ONLY)**

```sql
CREATE TABLE product_variants (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- External Reference
    external_id VARCHAR(100) NOT NULL UNIQUE,
    
    -- Product
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    
    -- Variant Details
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255),
    
    -- Specifications
    size VARCHAR(50),
    width DECIMAL(6,2),
    diameter DECIMAL(6,2),
    bolt_pattern VARCHAR(50),
    offset VARCHAR(20),
    center_bore DECIMAL(6,2),
    load_rating VARCHAR(50),
    weight DECIMAL(8,2),
    
    -- Pricing
    retail_price DECIMAL(10,2),
    wholesale_price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Sync Info
    last_synced_at TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_variants_product ON product_variants(product_id);
CREATE INDEX idx_variants_external ON product_variants(external_id);
CREATE INDEX idx_variants_sku ON product_variants(sku);
```

### **8. PRODUCT INVENTORIES (Synced from TunerStop + Wholesale - READ ONLY)**

```sql
CREATE TABLE product_inventories (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Product Reference
    product_id BIGINT REFERENCES products(id),
    product_variant_id BIGINT REFERENCES product_variants(id),
    addon_id BIGINT REFERENCES add_ons(id),
    
    -- Warehouse
    warehouse_id BIGINT NOT NULL REFERENCES warehouses(id),
    
    -- Source System
    source_system VARCHAR(20) CHECK (source_system IN ('retail', 'wholesale')),
    
    -- Quantity
    quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT DEFAULT 0,
    available_quantity INT GENERATED ALWAYS AS (quantity - reserved_quantity) STORED,
    
    -- ETA
    eta DATE,
    
    -- Sync Info
    last_synced_at TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    -- Ensure one record per item per warehouse
    CONSTRAINT uq_inventory_item_warehouse UNIQUE (product_id, product_variant_id, addon_id, warehouse_id)
);

CREATE INDEX idx_inventories_product ON product_inventories(product_id);
CREATE INDEX idx_inventories_variant ON product_inventories(product_variant_id);
CREATE INDEX idx_inventories_warehouse ON product_inventories(warehouse_id);
CREATE INDEX idx_inventories_available ON product_inventories(available_quantity) WHERE available_quantity > 0;
```

### **9. CONSIGNMENTS (CRM-Managed)**

```sql
CREATE TABLE consignments (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Consignment Details
    consignment_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Customer
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
    
    -- Status
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN (
        'draft', 'sent', 'delivered', 'partially_sold', 'invoiced', 'returned', 'cancelled'
    )),
    
    -- Dates
    sent_date DATE,
    delivered_date DATE,
    due_date DATE,
    
    -- Totals
    total_items_sent INT DEFAULT 0,
    total_items_sold INT DEFAULT 0,
    total_items_returned INT DEFAULT 0,
    total_value DECIMAL(12,2) DEFAULT 0,
    
    -- Representative
    representative_id BIGINT REFERENCES users(id),
    
    -- Notes
    notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE INDEX idx_consignments_customer ON consignments(customer_id);
CREATE INDEX idx_consignments_status ON consignments(status);
CREATE INDEX idx_consignments_representative ON consignments(representative_id);
```

### **10. INVOICES (Generated from Quotes/Orders)**

```sql
CREATE TABLE invoices (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Invoice Details
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Customer
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
    
    -- Source
    order_id BIGINT REFERENCES orders(id),
    quote_id BIGINT REFERENCES quotes(id),
    consignment_id BIGINT REFERENCES consignments(id),
    
    -- Financial
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    outstanding_amount DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    
    -- Expenses (for profit calculation)
    total_expenses DECIMAL(12,2) DEFAULT 0,
    gross_profit DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - total_expenses) STORED,
    profit_margin DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE WHEN total_amount > 0 THEN ((total_amount - total_expenses) / total_amount * 100) ELSE 0 END
    ) STORED,
    
    -- Status
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN (
        'draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'
    )),
    
    -- Dates
    invoice_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE,
    paid_date DATE,
    
    -- External Accounting (Wafeq)
    wafeq_id VARCHAR(100),
    synced_to_wafeq BOOLEAN DEFAULT FALSE,
    wafeq_synced_at TIMESTAMP,
    
    -- Representative
    representative_id BIGINT REFERENCES users(id),
    
    -- Notes
    notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE INDEX idx_invoices_customer ON invoices(customer_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_order ON invoices(order_id);
CREATE INDEX idx_invoices_quote ON invoices(quote_id);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);
```

### **11. WARRANTY CLAIMS (CRM-Managed)**

```sql
CREATE TABLE warranty_claims (
    -- Primary
    id BIGSERIAL PRIMARY KEY,
    
    -- Claim Details
    claim_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Customer & Order
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
    order_id BIGINT REFERENCES orders(id),
    
    -- Priority & Status
    priority VARCHAR(20) DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high', 'critical')),
    status VARCHAR(30) DEFAULT 'draft' CHECK (status IN (
        'draft', 'submitted', 'under_review', 'approved', 'rejected', 'resolved', 'closed'
    )),
    
    -- Issue Details
    issue_description TEXT NOT NULL,
    root_cause TEXT,
    resolution_type VARCHAR(30) CHECK (resolution_type IN (
        'replacement', 'refund', 'repair', 'no_action'
    )),
    resolution_description TEXT,
    
    -- SLA Tracking
    sla_hours INT DEFAULT 48, -- Based on priority
    sla_deadline TIMESTAMP,
    resolved_at TIMESTAMP,
    sla_met BOOLEAN GENERATED ALWAYS AS (
        resolved_at IS NULL OR resolved_at <= sla_deadline
    ) STORED,
    
    -- Costs
    total_cost DECIMAL(10,2) DEFAULT 0,
    supplier_recoverable_cost DECIMAL(10,2) DEFAULT 0,
    
    -- Customer Satisfaction
    customer_satisfaction INT CHECK (customer_satisfaction BETWEEN 1 AND 5),
    customer_feedback TEXT,
    
    -- Representative
    representative_id BIGINT REFERENCES users(id),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    submitted_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_warranty_customer ON warranty_claims(customer_id);
CREATE INDEX idx_warranty_status ON warranty_claims(status);
CREATE INDEX idx_warranty_priority ON warranty_claims(priority);
CREATE INDEX idx_warranty_sla_deadline ON warranty_claims(sla_deadline);
```

### **12. CUSTOMER PRICING RULES (CRM-Managed)**

```sql
-- Brand-level pricing
CREATE TABLE customer_brand_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    brand_id BIGINT NOT NULL REFERENCES brands(id) ON DELETE CASCADE,
    discount_percentage DECIMAL(5,2) NOT NULL CHECK (discount_percentage >= 0 AND discount_percentage <= 100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT uq_customer_brand_pricing UNIQUE (customer_id, brand_id)
);

-- Model-level pricing
CREATE TABLE customer_model_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    model_id BIGINT NOT NULL REFERENCES models(id) ON DELETE CASCADE,
    discount_percentage DECIMAL(5,2) NOT NULL CHECK (discount_percentage >= 0 AND discount_percentage <= 100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT uq_customer_model_pricing UNIQUE (customer_id, model_id)
);

-- AddOn category pricing
CREATE TABLE customer_addon_category_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    addon_category_id BIGINT NOT NULL REFERENCES add_on_categories(id) ON DELETE CASCADE,
    discount_percentage DECIMAL(5,2) NOT NULL CHECK (discount_percentage >= 0 AND discount_percentage <= 100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT uq_customer_addon_pricing UNIQUE (customer_id, addon_category_id)
);
```

---

## 🔄 Sync Flow Architecture

### **1. Product Sync (TunerStop → CRM)**

```sql
-- Sync operation tracking
CREATE TABLE sync_operations (
    id BIGSERIAL PRIMARY KEY,
    operation_type VARCHAR(50) NOT NULL, -- 'product_sync', 'order_sync', etc.
    source_system VARCHAR(20) NOT NULL, -- 'retail', 'wholesale'
    external_id VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
    error_message TEXT,
    retry_count INT DEFAULT 0,
    payload JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);

CREATE INDEX idx_sync_ops_status ON sync_operations(status);
CREATE INDEX idx_sync_ops_type ON sync_operations(operation_type);
```

### **2. Quote to Order Conversion Flow**

```sql
-- Trigger to update quote when order is created
CREATE OR REPLACE FUNCTION link_order_to_quote()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.quote_id IS NOT NULL THEN
        UPDATE quotes 
        SET 
            quote_status = 'converted',
            converted_to_order_id = NEW.id,
            converted_at = NOW()
        WHERE id = NEW.quote_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_link_order_to_quote
AFTER INSERT ON orders
FOR EACH ROW
WHEN (NEW.quote_id IS NOT NULL)
EXECUTE FUNCTION link_order_to_quote();
```

---

## 📊 Views for Reporting

### **1. Unified Orders View (Quotes + Orders)**

```sql
CREATE VIEW vw_unified_orders AS
SELECT 
    'quote' as document_type,
    q.id,
    q.quote_number as document_number,
    q.customer_id,
    q.quote_status as status,
    q.total_amount,
    NULL as paid_amount,
    q.created_at,
    q.representative_id,
    q.vehicle_year,
    q.vehicle_make,
    q.vehicle_model
FROM quotes q
WHERE q.deleted_at IS NULL

UNION ALL

SELECT 
    'order' as document_type,
    o.id,
    o.order_number as document_number,
    o.customer_id,
    o.order_status as status,
    o.total_amount,
    o.paid_amount,
    o.created_at,
    o.representative_id,
    o.vehicle_year,
    o.vehicle_make,
    o.vehicle_model
FROM orders o;
```

### **2. Dashboard Statistics View**

```sql
CREATE MATERIALIZED VIEW mv_dashboard_stats AS
SELECT 
    DATE_TRUNC('day', created_at) as date,
    COUNT(*) FILTER (WHERE document_type = 'quote' AND status IN ('draft', 'sent')) as pending_quotes,
    COUNT(*) FILTER (WHERE document_type = 'order' AND status IN ('pending', 'processing')) as pending_orders,
    SUM(total_amount) FILTER (WHERE document_type = 'order' AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)) as monthly_revenue,
    COUNT(*) FILTER (WHERE DATE_TRUNC('day', created_at) = CURRENT_DATE) as today_count
FROM vw_unified_orders
GROUP BY DATE_TRUNC('day', created_at);

CREATE INDEX idx_mv_dashboard_stats_date ON mv_dashboard_stats(date);

-- Refresh daily
CREATE OR REPLACE FUNCTION refresh_dashboard_stats()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_dashboard_stats;
END;
$$ LANGUAGE plpgsql;
```

---

## 🎯 Key Differences from Old Design

| Aspect | Old System (Messy) | New System (Clean) |
|--------|-------------------|-------------------|
| Products | CRM manages own products | CRM syncs from TunerStop (read-only) |
| Inventory | CRM manages inventory | CRM syncs from TunerStop + Wholesale (read-only) |
| Orders | CRM creates orders | CRM syncs orders from external systems |
| Quotes | Mixed with orders | Dedicated `quotes` table with conversion workflow |
| Upload | Client uploads twice | Client uploads once, CRM auto-syncs |
| Master Data | Duplicated everywhere | Single source of truth |
| Complexity | Over-engineered | Clean, simple, focused |

---

## ✅ Benefits of New Design

1. **Single Source of Truth**
   - TunerStop = Master for products/inventory
   - CRM = Master for quotes/relationships

2. **No Data Duplication**
   - Client uploads once
   - CRM auto-syncs
   - Always in sync

3. **Clear Responsibilities**
   - CRM manages what it should: Quotes, Customers, Relationships
   - External systems manage: Products, Inventory, Orders

4. **Better Performance**
   - Less data to manage in CRM
   - Faster queries
   - Simpler logic

5. **Easier Maintenance**
   - Clear data ownership
   - Less code
   - Fewer bugs

---

**END OF DATABASE DESIGN**
