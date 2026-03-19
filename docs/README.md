# Reporting CRM v2.0 - Documentation

Welcome to the Reporting CRM v2.0 documentation. This directory contains all technical documentation, architecture decisions, and implementation guides.

Historical root-level notes and completion reports are now consolidated under `docs/root/` to keep the repository root cleaner.

---

## 📚 Documentation Structure

```
docs/
├── README.md                     # This file - documentation index
├── IMPLEMENTATION_PLAN.md        # 16-week phased development plan
├── PROGRESS.md                  # Current progress snapshot
├── architecture/                # Architecture documentation
├── root/                        # Historical root-level notes and completion reports
└── ...                          # Module notes, plans, and implementation reports
    ├── ARCHITECTURE_MASTER_INDEX.md
    ├── ARCHITECTURE_SUMMARY.md
    ├── NEW_SYSTEM_ARCHITECTURE.md
    ├── DATABASE_DESIGN.md
    ├── RESEARCH_FINDINGS.md
    └── [Module-specific architecture docs]
```

---

## 🚀 Quick Start

1. **New Developer Onboarding:**
   - Read: `ARCHITECTURE_MASTER_INDEX.md` (Overview)
   - Read: `NEW_SYSTEM_ARCHITECTURE.md` (System design)
   - Read: `IMPLEMENTATION_PLAN.md` (Development roadmap)

2. **Implementation Team:**
   - Start with: `PROGRESS.md` (current state and recent work)
   - Follow: `IMPLEMENTATION_PLAN.md` (Week-by-week guide)

3. **Module Development:**
   - Check: `architecture/ARCHITECTURE_[MODULE]_MODULE.md`
   - Follow: Module-specific guidelines and patterns

---

## 📖 Core Documentation

### Planning & Implementation
- **[IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md)** - 16-week phased development plan with daily tasks
- **[PROGRESS.md](./PROGRESS.md)** - Current project progress snapshot

### Architecture Overview
- **[ARCHITECTURE_MASTER_INDEX.md](./architecture/ARCHITECTURE_MASTER_INDEX.md)** - Complete architecture overview and module index
- **[ARCHITECTURE_SUMMARY.md](./architecture/ARCHITECTURE_SUMMARY.md)** - High-level architecture summary with critical corrections
- **[NEW_SYSTEM_ARCHITECTURE.md](./architecture/NEW_SYSTEM_ARCHITECTURE.md)** - Comprehensive system architecture and design patterns

### Database & Design
- **[DATABASE_DESIGN.md](./architecture/DATABASE_DESIGN.md)** - Complete database schema and relationships
- **[RESEARCH_FINDINGS.md](./architecture/RESEARCH_FINDINGS.md)** - Research outcomes and architectural decisions

### Workflow & Processes
- **[CRITICAL_WORKFLOW_CORRECTION.md](./architecture/CRITICAL_WORKFLOW_CORRECTION.md)** - Critical workflow patterns and corrections
- **[DASHBOARD_AND_QUOTE_WORKFLOW.md](./architecture/DASHBOARD_AND_QUOTE_WORKFLOW.md)** - Quote-to-Order workflow documentation
- **[UNDERSTANDING_CONFIRMATION.md](./architecture/UNDERSTANDING_CONFIRMATION.md)** - Architecture understanding validation

---

## 🏗️ Module Architecture Documentation

### Core Business Modules
1. **[ARCHITECTURE_ORDERS_MODULE.md](./architecture/ARCHITECTURE_ORDERS_MODULE.md)**
   - Unified orders table with document_type ENUM
   - Quote, Invoice, Order handling
   - PaymentRecord model
   - Wafeq accounting sync

2. **[ARCHITECTURE_CUSTOMERS_MODULE.md](./architecture/ARCHITECTURE_CUSTOMERS_MODULE.md)**
   - Customer management
   - DealerPricingService implementation
   - Dealer pricing hierarchy

3. **[ARCHITECTURE_PRODUCTS_MODULE.md](./architecture/ARCHITECTURE_PRODUCTS_MODULE.md)**
   - Product catalog (reference-only)
   - ProductSnapshotService
   - On-demand sync strategy

4. **[ARCHITECTURE_VARIANTS_MODULE.md](./architecture/ARCHITECTURE_VARIANTS_MODULE.md)**
   - Product variants
   - VariantSnapshotService
   - Dealer pricing for variants

5. **[ARCHITECTURE_ADDONS_MODULE.md](./architecture/ARCHITECTURE_ADDONS_MODULE.md)**
   - AddOns catalog
   - AddonSnapshotService
   - Category-based pricing

### Financial & Inventory Modules
6. **[ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md](./architecture/ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md)**
   - Consignment tracking
   - Invoice management with 7 expense categories
   - Warranty system
   - Financial transaction recording

7. **[ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md](./architecture/ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md)**
   - Warehouse management
   - Inventory tracking (reference-only)
   - InventoryLog for stock movements

### Integration & Sync
8. **[ARCHITECTURE_SYNC_PROCESSES.md](./architecture/ARCHITECTURE_SYNC_PROCESSES.md)**
   - WafeqSyncService architecture
   - Queue-based sync strategy
   - Snapshot-based data capture
   - Retry logic and error handling

---

## 🎯 Key Architecture Decisions

### 1. Unified Orders Table
- **Decision:** Single `orders` table with `document_type` ENUM('quote', 'invoice', 'order')
- **Why:** Simplified quote-to-order conversion, reduced data duplication
- **Impact:** All order-related operations use same table structure

### 2. Snapshot-Based Sync
- **Decision:** Capture product/variant/addon data in JSONB at order time
- **Why:** Maintain historical accuracy, no dependency on live catalog
- **Impact:** ProductSnapshotService, VariantSnapshotService, AddonSnapshotService

### 3. Dealer Pricing Service
- **Decision:** Centralized pricing with priority hierarchy (variant → model → brand → customer default)
- **Why:** Consistent pricing across all modules
- **Impact:** DealerPricingService used by Products, Variants, AddOns, Orders

### 4. Tax Per Item
- **Decision:** `tax_inclusive` boolean on each line item, not order-level
- **Why:** Some items may be tax-inclusive, others tax-exclusive
- **Impact:** Tax calculation per item in order_items, order_addon_items

### 5. Wafeq Queue-Based Sync
- **Decision:** All accounting syncs use Laravel queues with retry logic
- **Why:** Reliability, error handling, async processing
- **Impact:** SyncPaymentToWafeq, SyncInvoiceToWafeq, SyncExpenseToWafeq jobs

### 6. Laravel 12 (LTS)
- **Decision:** Use Laravel 12 (latest LTS, March 2024 release)
- **Why:** 2 years bug fixes, 3 years security updates, modern features
- **Impact:** Long-term support, stable foundation

### 7. PostgreSQL with JSONB
- **Decision:** PostgreSQL 15 as primary database
- **Why:** Superior JSONB support for snapshots, better performance
- **Impact:** Snapshot data stored efficiently, queryable JSON fields

### 8. Settings Module Priority
- **Decision:** Build Settings module first (Week 1)
- **Why:** Required for all PDFs, invoices, quotes, consignment templates
- **Impact:** Tax rates, currency, branding logo, company details centralized

---

## 🛠️ Technology Stack

### Backend Framework
- **Laravel 12 (LTS)** - PHP framework (March 2024 release)
- **PostgreSQL 15** - Primary database with JSONB support
- **Redis** - Caching and queue driver
- **Laravel Horizon** - Queue monitoring

### Admin Panel
- **Filament v3** - Modern admin panel framework
- **Livewire** - Dynamic UI components
- **Alpine.js** - Frontend interactivity

### Data Grid
- **pqGrid Pro** - Excel-like data grids for complex data entry

### External Integrations
- **Wafeq API** - Accounting system integration
- **AWS S3** - File storage for images and documents
- **Meilisearch** - Fast search functionality

### Development Tools
- **Pest** - Testing framework
- **Laravel Pint** - Code formatting
- **Larastan** - Static analysis
- **Debugbar** - Development debugging

---

## 📋 Module Overview (13 Modules)

| # | Module | Priority | Week | Status |
|---|--------|----------|------|--------|
| 1 | **Settings** | Must Have | 1 | 🔄 Planned |
| 2 | **Customers** | Must Have | 3 | 📅 Week 3 |
| 3 | **Products** | Must Have | 4 | 📅 Week 4 |
| 4 | **Variants** | Must Have | 5 | 📅 Week 5 |
| 5 | **AddOns** | Must Have | 5 | 📅 Week 5 |
| 6 | **Quotes** | Must Have | 6 | 📅 Week 6 |
| 7 | **Orders** | Must Have | 6 | 📅 Week 6 |
| 8 | **Invoices** | Must Have | 8 | 📅 Week 8 |
| 9 | **Warehouse** | Should Have | 7 | 📅 Week 7 |
| 10 | **Consignment** | Should Have | 9 | 📅 Week 9 |
| 11 | **Warranty** | Should Have | 10 | 📅 Week 10 |
| 12 | **Inventory** | Nice to Have | 7 | 📅 Week 7 |
| 13 | **Reports** | Nice to Have | 11-12 | 📅 Week 11-12 |

---

## 🔧 Critical Services

### Must Build First (Week 2)
1. **DealerPricingService** - Centralized pricing logic
2. **ProductSnapshotService** - Capture product data at order time
3. **VariantSnapshotService** - Capture variant data at order time
4. **AddonSnapshotService** - Capture addon data at order time
5. **WafeqSyncService** - Queue-based accounting sync
6. **SettingsService** - System-wide configuration with caching

### Supporting Services
7. **OrderConversionService** - Quote to Order conversion
8. **InvoiceGenerationService** - Generate invoices from orders
9. **ProfitCalculationService** - Calculate profit margins
10. **InventoryService** - Track stock movements (reference-only)

---

## 🗄️ Database Schema Summary

### Core Tables
- `customers` - Customer master data
- `dealer_pricing` - Dealer-specific pricing rules
- `products` - Product catalog (reference only)
- `variants` - Product variants (reference only)
- `addons` - AddOns catalog (reference only)

### Orders & Financial
- `orders` - Unified table with document_type ENUM
- `order_items` - Line items with product snapshots (JSONB)
- `order_addon_items` - Addon items with snapshots (JSONB)
- `payment_records` - Payment tracking with auto-generated numbers
- `invoices` - Extended from orders with expense columns
- `financial_transactions` - Transaction log

### Settings & Configuration
- `tax_settings` - Tax rates and defaults
- `currency_settings` - Base currency and exchange rates
- `company_branding` - Logo, colors, company details

### Warehouse & Inventory
- `warehouses` - Warehouse locations
- `inventory_logs` - Stock movement tracking

### Integration
- `wafeq_sync_queue` - Queue for Wafeq accounting sync
- `sync_logs` - Integration sync history

---

## 📊 Development Timeline

```
Week 1-2:   Foundation (Laravel 12, Filament, Settings Module)
Week 3:     Customers Module + DealerPricingService
Week 4:     Products Module + ProductSnapshotService
Week 5:     Variants + AddOns Modules + Snapshot Services
Week 6:     Quotes & Orders (Unified Table)
Week 7:     Warehouse & Inventory
Week 8:     Invoices + Wafeq Integration
Week 9:     Consignment Module
Week 10:    Warranty Module
Week 11-12: Reports & Analytics
Week 13-14: Integration Testing
Week 15-16: UAT & Deployment
```

---

## 🧪 Testing Strategy

### Unit Tests
- Service classes (DealerPricingService, SnapshotServices, etc.)
- Model methods and relationships
- Helper functions and utilities

### Feature Tests
- CRUD operations for all modules
- Quote-to-Order conversion
- Wafeq sync jobs
- Financial calculations

### Integration Tests
- Complete workflows (Quote → Order → Invoice)
- Payment recording and sync
- Inventory tracking
- Multi-module interactions

### Browser Tests
- Admin panel workflows
- Form submissions
- Data grid interactions
- PDF generation

---

## 📝 Contributing

### Code Standards
- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting
- Run Larastan for static analysis
- Write tests for new features

### Git Workflow
- `main` - Production-ready code
- `develop` - Integration branch
- `feature/*` - Feature branches
- `bugfix/*` - Bug fix branches

### Documentation Updates
- Update module architecture docs when changing structure
- Keep IMPLEMENTATION_PLAN.md in sync with actual progress
- Document all major architectural decisions

---

## 🔗 Related Resources

- **GitHub Repository:** https://github.com/dev-hub-stack/tuerstop-reporting.git
- **Laravel 12 Docs:** https://laravel.com/docs/12.x
- **Filament v3 Docs:** https://filamentphp.com/docs/3.x
- **PostgreSQL Docs:** https://www.postgresql.org/docs/15/

---

## 📞 Support & Contact

For questions or clarifications:
- Review architecture documents first
- Check IMPLEMENTATION_PLAN.md for task details
- Refer to module-specific architecture docs

---

**Last Updated:** October 20, 2025  
**Version:** 2.0  
**Status:** Phase 1 - Foundation Setup
