# Implementation Plan - Reporting CRM v2.0
## Phased Development Plan with Specific TODOs

**Project Duration:** 16 weeks (4 months)  
**Team Size:** 2-3 developers  
**Start Date:** November 1, 2025  
**Target Launch:** March 1, 2026  

**Updated:** October 20, 2025 - Aligned with latest architecture decisions

**Key Architecture Changes:**
- ✅ Laravel 12 (LTS) - Latest version
- ✅ Unified Orders Table with document_type ENUM
- ✅ Snapshot-based Product/Variant Sync (not full catalog)
- ✅ Separate Quotes Module
- ✅ Settings Module for system configuration
- ✅ Wafeq Accounting Integration with queue-based sync
- ✅ Dealer Pricing via DealerPricingService
- ✅ Tax Inclusive/Exclusive per item

---

## 📋 Development Phases Overview

```
Phase 1: Foundation & Setup          [Weeks 1-2]  ████░░░░░░░░░░░░ 12.5%
Phase 2: Core Modules               [Weeks 3-6]  ████████░░░░░░░░ 50%
Phase 3: Secondary Modules          [Weeks 7-10] ████████████░░░░ 75%
Phase 4: Integration & Polish       [Weeks 11-14]████████████████ 87.5%
Phase 5: Testing & Deployment       [Weeks 15-16]████████████████ 100%
```

---

## 🚀 PHASE 1: Foundation & Setup (Weeks 1-2)

### **Week 1: Project Setup & Core Infrastructure**

#### **Day 1-2: Project Initialization**
- [ ] Create new Laravel 12 project (LTS - Released March 2024)
  ```bash
  composer create-project laravel/laravel:^12.0 reporting-crm-v2
  cd reporting-crm-v2
  ```
- [ ] Setup Git repository
  ```bash
  git init
  git remote add origin https://github.com/dev-hub-stack/reporting-crm-v2.git
  git checkout -b develop
  ```
- [ ] Configure `.env` file (PostgreSQL, Redis, S3, Meilisearch)
- [ ] Install core dependencies
  ```bash
  composer require filament/filament:"^3.0" \
      spatie/laravel-permission \
      spatie/laravel-activitylog \
      spatie/laravel-medialibrary \
      maatwebsite/excel \
      laravel/sanctum \
      laravel/horizon
  ```
- [ ] Install dev dependencies
  ```bash
  composer require --dev pestphp/pest \
      pestphp/pest-plugin-laravel \
      larastan/larastan \
      laravel/pint \
      barryvdh/laravel-debugbar
  ```
- [ ] Setup Filament admin panel
  ```bash
  php artisan filament:install --panels
  ```

#### **Day 3-4: Project Structure Setup**
- [ ] Create modular directory structure
  ```bash
  # Create Modules directory
  mkdir -p app/Modules/{Quotes,Orders,Customers,Products,Variants,Inventory,AddOns,Consignment,Invoices,Warranty,Warehouse,Sync,Settings}
  
  # Create subdirectories for each module
  for module in Quotes Orders Customers Products Variants Inventory AddOns Consignment Invoices Warranty Warehouse Sync Settings; do
      mkdir -p app/Modules/$module/{Actions,DTOs,Enums,Events,Listeners,Models,Policies,Repositories,Services,Filament,Livewire,Jobs,Observers}
  done
  
  # Create Core directory
  mkdir -p app/Core/{Contracts,Enums,Exceptions,Traits,ValueObjects}
  
  # Create Shared directory
  mkdir -p app/Shared/{Services,Traits,Utilities}
  ```
- [ ] Configure PSR-4 autoloading in `composer.json`
  ```json
  "autoload": {
      "psr-4": {
          "App\\": "app/",
          "App\\Modules\\": "app/Modules/",
          "App\\Core\\": "app/Core/",
          "App\\Shared\\": "app/Shared/"
      }
  }
  ```
- [ ] Run `composer dump-autoload`

#### **Day 5: Database & Configuration**
- [ ] Setup PostgreSQL database
- [ ] Configure database connection in `.env`
- [ ] Setup Redis for cache and queue
  ```env
  CACHE_DRIVER=redis
  QUEUE_CONNECTION=redis
  SESSION_DRIVER=redis
  ```
- [ ] Setup S3 for file storage
  ```env
  FILESYSTEM_DISK=s3
  AWS_ACCESS_KEY_ID=your-key
  AWS_SECRET_ACCESS_KEY=your-secret
  AWS_DEFAULT_REGION=us-east-1
  AWS_BUCKET=reporting-crm-uploads
  ```
- [ ] Install and configure Meilisearch
  ```bash
  composer require laravel/scout meilisearch/meilisearch-php
  php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
  ```

#### **Day 6-7: Core Enums & Base Classes**
- [ ] Create core enums
  - [ ] `app/Core/Enums/Role.php`
  - [ ] `app/Core/Enums/Permission.php`
  - [ ] `app/Core/Enums/Status.php`
- [ ] Create base repository interface
  - [ ] `app/Core/Contracts/RepositoryInterface.php`
- [ ] Create base repository implementation
  - [ ] `app/Core/Repositories/BaseRepository.php`
- [ ] Create base action class
  - [ ] `app/Core/Actions/BaseAction.php`
- [ ] Create base DTO class
  - [ ] `app/Core/DTOs/BaseDTO.php`
- [ ] Create shared traits
  - [ ] `app/Shared/Traits/HasUuid.php`
  - [ ] `app/Shared/Traits/HasAuditLog.php`
  - [ ] `app/Shared/Traits/HasAddresses.php`

---

### **Week 2: Authentication, Roles & pqGrid Setup**

#### **Day 8-9: Authentication & Authorization**
- [ ] Setup Spatie Laravel Permission
  ```bash
  php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
  php artisan migrate
  ```
- [ ] Create roles seeder
  - [ ] `database/seeders/RoleSeeder.php`
- [ ] Create permissions seeder
  - [ ] `database/seeders/PermissionSeeder.php`
- [ ] Create admin user seeder
  - [ ] `database/seeders/AdminUserSeeder.php`
- [ ] Setup Filament authentication
- [ ] Configure role-based access in Filament
- [ ] Create middleware for API authentication
  - [ ] `app/Http/Middleware/CheckApiToken.php`

#### **Day 10-11: pqGrid Integration**
- [ ] Copy pqGrid library to `public/pqgrid/`
- [ ] Create grid helper utility
  - [ ] `resources/js/grid-helpers.js`
- [ ] Create base Livewire grid component
  - [ ] `app/Shared/Livewire/BaseGrid.php`
- [ ] Create blade component for grid wrapper
  - [ ] `resources/views/components/grid-wrapper.blade.php`
- [ ] Test basic grid functionality
- [ ] Document pqGrid integration patterns

#### **Day 12-14: Docker & Development Environment**
- [ ] Create `Dockerfile`
  ```dockerfile
  FROM php:8.3-fpm
  
  # Install dependencies
  RUN apt-get update && apt-get install -y \
      git curl zip unzip \
      libpng-dev libjpeg-dev libfreetype6-dev \
      libpq-dev libzip-dev
  
  # PHP extensions
  RUN docker-php-ext-install pdo pdo_pgsql gd zip
  
  # Composer
  COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
  
  WORKDIR /var/www
  ```
- [ ] Create `docker-compose.yml`
  ```yaml
  version: '3.8'
  services:
    app:
      build: .
      volumes:
        - .:/var/www
      networks:
        - crm-network
    
    postgres:
      image: postgres:15
      environment:
        POSTGRES_DB: reporting_crm
        POSTGRES_USER: crm_user
        POSTGRES_PASSWORD: secret
      ports:
        - "5432:5432"
      networks:
        - crm-network
    
    redis:
      image: redis:7-alpine
      ports:
        - "6379:6379"
      networks:
        - crm-network
    
    meilisearch:
      image: getmeili/meilisearch:latest
      ports:
        - "7700:7700"
      networks:
        - crm-network
    
    nginx:
      image: nginx:alpine
      ports:
        - "80:80"
      volumes:
        - ./nginx.conf:/etc/nginx/nginx.conf
        - .:/var/www
      networks:
        - crm-network
  
  networks:
    crm-network:
      driver: bridge
  ```
- [ ] Create nginx configuration
- [ ] Test Docker environment
- [ ] Document setup process

---

## 🔧 PHASE 2: Core Modules (Weeks 3-6)

### **Week 3: Settings Module & Customers Module**

#### **Day 15-16: Settings Module (NEW)**
- [ ] Create migrations
  - [ ] `create_settings_table`
    - id, category, key, value, type, description, is_public
  - [ ] `create_company_branding_table`
    - id, company_name, company_logo, company_address, company_phone, company_email, tax_registration_number, primary_color, secondary_color
  - [ ] `create_document_templates_table`
    - id, name, type, template_path, is_default, config (JSONB)
- [ ] Run migrations
- [ ] Create models
  - [ ] `app/Modules/Settings/Models/Setting.php`
  - [ ] `app/Modules/Settings/Models/CompanyBranding.php`
  - [ ] `app/Modules/Settings/Models/DocumentTemplate.php`
- [ ] Create services
  - [ ] `app/Modules/Settings/Services/SettingsService.php`
    - getTaxRate(), getCurrency(), getCompanyLogo()
    - getAllTaxSettings(), getAllCurrencySettings()
    - updateSettings(), clearCache()
- [ ] Create Filament resource
  - [ ] `app/Modules/Settings/Filament/Resources/SettingsResource.php`
  - Multi-tab interface (Tax, Currency, Branding, Templates, Integrations)
- [ ] Create default settings seeder
  - [ ] `database/seeders/DefaultSettingsSeeder.php`
    - Tax rate: 5% (VAT)
    - Currency: AED
    - Template defaults
- [ ] Test settings CRUD
- [ ] Test cache clearing

#### **Day 17-18: Customers Module - Database & Models**
- [ ] Create migrations
  - [ ] `create_customers_table`
    - id, name, email, phone, type, business_name, trn, license, representative_id
  - [ ] `create_address_books_table`
    - id, customer_id, type, address_line1, address_line2, city, state, country_id, zip, is_default
  - [ ] `create_customer_brand_pricing_table`
    - id, customer_id, brand_id, discount_percentage
  - [ ] `create_customer_model_pricing_table`
  - [ ] `create_customer_addon_category_pricing_table`
  - [ ] `create_countries_table`
  - [ ] `create_states_table`
- [ ] Run migrations
  ```bash
  php artisan migrate
  ```
- [ ] Create models
  - [ ] `app/Modules/Customers/Models/Customer.php`
  - [ ] `app/Modules/Customers/Models/AddressBook.php`
  - [ ] `app/Modules/Customers/Models/CustomerBrandPricing.php`
  - [ ] `app/Modules/Customers/Models/CustomerModelPricing.php`
  - [ ] `app/Modules/Customers/Models/CustomerAddonCategoryPricing.php`
  - [ ] `app/Modules/Customers/Models/Country.php`
  - [ ] `app/Modules/Customers/Models/State.php`
- [ ] Define model relationships
- [ ] Add soft deletes to Customer model
- [ ] Create model factories for testing

#### **Day 17-18: Business Logic**
- [ ] Create enums
  - [ ] `app/Modules/Customers/Enums/CustomerType.php` (Retail, Dealer)
  - [ ] `app/Modules/Customers/Enums/AddressType.php` (Billing, Shipping)
- [ ] Create DTOs
  - [ ] `app/Modules/Customers/DTOs/CustomerData.php`
  - [ ] `app/Modules/Customers/DTOs/AddressData.php`
  - [ ] `app/Modules/Customers/DTOs/PricingRuleData.php`
- [ ] Create repository
  - [ ] `app/Modules/Customers/Repositories/CustomerRepository.php`
- [ ] Create services
  - [ ] `app/Modules/Customers/Services/CustomerService.php`
  - [ ] `app/Modules/Customers/Services/CustomerPricingService.php`
- [ ] Create actions
  - [ ] `app/Modules/Customers/Actions/CreateCustomerAction.php`
  - [ ] `app/Modules/Customers/Actions/UpdateCustomerAction.php`
  - [ ] `app/Modules/Customers/Actions/ApplyPricingRulesAction.php`

#### **Day 19-20: Admin Interface**
- [ ] Create Filament resource
  - [ ] `app/Modules/Customers/Filament/Resources/CustomerResource.php`
- [ ] Configure form fields
  - Customer info fields
  - Address relation manager
  - Pricing rules relation manager
- [ ] Configure table columns
- [ ] Add filters (type, representative)
- [ ] Create custom actions
  - View orders
  - View invoices
  - Export customer data
- [ ] Test CRUD operations

#### **Day 21: Testing & Documentation**
- [ ] Write feature tests
  - [ ] `tests/Feature/Customers/CreateCustomerTest.php`
  - [ ] `tests/Feature/Customers/UpdateCustomerTest.php`
  - [ ] `tests/Feature/Customers/CustomerPricingTest.php`
- [ ] Write unit tests for services
- [ ] Create API endpoints
  - [ ] GET `/api/v1/customers`
  - [ ] POST `/api/v1/customers`
  - [ ] GET `/api/v1/customers/{id}`
  - [ ] PUT `/api/v1/customers/{id}`
- [ ] Document module in README

---

### **Week 4: Products Module & Sync**

#### **Day 22-23: Database & Models**
- [ ] Create migrations
  - [ ] `create_brands_table`
  - [ ] `create_models_table` (vehicle models)
  - [ ] `create_finishes_table`
  - [ ] `create_products_table`
    - id, name, slug, brand_id, model_id, finish_id, description, retail_price, wholesale_price, sync_status
  - [ ] `create_product_images_table`
  - [ ] `create_brand_mappings_table` (for sync)
  - [ ] `create_model_mappings_table`
  - [ ] `create_finish_mappings_table`
- [ ] Create models with relationships
  - [ ] `Product.php`
  - [ ] `Brand.php`
  - [ ] `VehicleModel.php`
  - [ ] `Finish.php`
  - [ ] `ProductImage.php`
  - [ ] `BrandMapping.php`
  - [ ] `ModelMapping.php`
  - [ ] `FinishMapping.php`
- [ ] Setup Laravel Scout for search
- [ ] Create seeders for brands, models, finishes

#### **Day 24-25: Product Sync Logic**
- [ ] Create sync DTOs
  - [ ] `ProductSyncData.php`
- [ ] Create mapping services
  - [ ] `BrandMappingService.php`
  - [ ] `ModelMappingService.php`
  - [ ] `FinishMappingService.php`
- [ ] Create sync service
  - [ ] `ProductSyncService.php`
    - UPSERT logic
    - Image syncing
    - Auto-create missing mappings
    - Error handling
- [ ] Create sync job
  - [ ] `SyncProductJob.php`
- [ ] Create sync controller
  - [ ] `ProductSyncController.php`
    - POST `/api/sync/product`
    - Authentication
    - Validation
    - Queue dispatch
- [ ] Test sync with sample data

#### **Day 26-27: Admin Interface with pqGrid**
- [ ] Create Filament resource
  - [ ] `ProductResource.php`
- [ ] Create standard form/table views
- [ ] Create Livewire pqGrid component
  - [ ] `ProductsGrid.php`
  - Inline editing
  - Bulk updates
  - Excel export
  - Copy/paste from Excel
  - Filtering
- [ ] Create custom Filament page for grid view
  - [ ] `ProductResource/Pages/ProductGridPage.php`
- [ ] Add navigation tabs (Form View / Grid View)
- [ ] Test grid performance with 10,000+ rows

#### **Day 28: Testing**
- [ ] Write feature tests
  - [ ] `ProductSyncTest.php`
  - [ ] `ProductCRUDTest.php`
  - [ ] `MappingServicesTest.php`
- [ ] Write unit tests
- [ ] Test API endpoints
- [ ] Performance testing for grid

---

### **Week 5: Product Variants & Inventory**

#### **Day 29-30: Variants Module**
- [ ] Create migration
  - [ ] `create_product_variants_table`
    - id, product_id, sku, size, width, diameter, bolt_pattern, offset, load_rating, weight, retail_price, wholesale_price
- [ ] Create model
  - [ ] `ProductVariant.php`
  - Add OutOfStockScope
  - Relationships
- [ ] Create DTO
  - [ ] `VariantData.php`
- [ ] Create service
  - [ ] `VariantService.php`
- [ ] Create actions
  - [ ] `ImportVariantsAction.php` (CSV import)
  - [ ] `UpdateVariantAction.php`
- [ ] Create Filament resource
  - [ ] `ProductVariantResource.php`
- [ ] Create Livewire grid component
  - [ ] `VariantsGrid.php`
  - Excel-like editing for specifications
- [ ] Test CSV import functionality

#### **Day 31-32: Inventory Module**
- [ ] Create migrations
  - [ ] `create_warehouses_table`
    - id, name, address, city, country, latitude, longitude, is_active
  - [ ] `create_product_inventories_table`
    - id, product_id, product_variant_id, addon_id, warehouse_id, quantity, eta, last_updated
- [ ] Create models
  - [ ] `Warehouse.php`
    - Add geolocation scopes (Haversine formula)
  - [ ] `ProductInventory.php`
- [ ] Create services
  - [ ] `InventoryService.php`
    - Update inventory
    - Allocate inventory
    - Transfer inventory
  - [ ] `WarehouseFulfillmentService.php`
    - Find nearest warehouse
    - Distance calculation
- [ ] Create actions
  - [ ] `UpdateInventoryAction.php`
  - [ ] `AllocateInventoryAction.php`
  - [ ] `TransferInventoryAction.php`

#### **Day 33-34: Inventory Admin & Grid**
- [ ] Create Filament resources
  - [ ] `WarehouseResource.php`
  - [ ] `InventoryResource.php`
- [ ] Create Livewire inventory grid
  - [ ] `InventoryGrid.php`
  - Multi-warehouse view
  - Bulk inventory updates
  - Low stock alerts
  - Excel export
- [ ] Create warehouse selector widget
- [ ] Create inventory dashboard widgets
  - Total stock value
  - Low stock items
  - Out of stock items
  - Warehouse utilization
- [ ] Test geolocation-based fulfillment

#### **Day 35: Testing**
- [ ] Write feature tests
  - [ ] `InventoryAllocationTest.php`
  - [ ] `WarehouseFulfillmentTest.php`
- [ ] Write unit tests
- [ ] Test geolocation accuracy
- [ ] Performance test for inventory queries

---

### **Week 6: Quotes & Orders Module (Unified Table Approach)**

#### **Day 36-37: Unified Orders Table & Quotes Module (NEW)**
- [ ] Create migrations
  - [ ] `create_orders_table` (UNIFIED for quotes, orders, invoices)
    - id, order_number, customer_id, **document_type** (ENUM: quote, order, invoice), order_status, payment_status, representative_id, total_amount, notes, tracking_number, external_order_id, wafeq_id
  - [ ] `create_order_items_table`
    - id, order_id, product_id, product_variant_id, addon_id, **product_snapshot** (JSONB), **variant_snapshot** (JSONB), sku, product_name, quantity, unit_price, **tax_inclusive** (boolean), discount, total_price
  - [ ] `create_order_item_quantities_table`
    - id, order_item_id, warehouse_id, quantity
- [ ] Create models
  - [ ] `app/Modules/Orders/Models/Order.php` (with document_type scope)
  - [ ] `app/Modules/Orders/Models/OrderItem.php`
  - [ ] `app/Modules/Orders/Models/OrderItemQuantity.php`
  - [ ] `app/Modules/Quotes/Models/Quote.php` (extends Order with document_type='quote')
- [ ] Create enums
  - [ ] `app/Modules/Orders/Enums/DocumentType.php` (Quote, Order, Invoice)
  - [ ] `app/Modules/Orders/Enums/OrderStatus.php` (Draft, Pending, Approved, Processing, Shipped, Completed, Cancelled)
  - [ ] `app/Modules/Orders/Enums/PaymentStatus.php` (Pending, Partial, Paid, Refunded)
  - [ ] `app/Modules/Quotes/Enums/QuoteStatus.php` (Draft, Sent, Approved, Rejected, Converted, Expired)
- [ ] Define relationships
- [ ] Add observers for order events

#### **Day 38: Snapshot Services (CRITICAL)**
- [ ] Create snapshot services
  - [ ] `app/Modules/Products/Services/ProductSnapshotService.php`
    - createSnapshot() - Capture product data at time of order
    - Denormalize brand_name, model_name, attributes
  - [ ] `app/Modules/Variants/Services/VariantSnapshotService.php`
    - createSnapshot() - Capture variant specs (size, bolt pattern, offset, etc.)
  - [ ] `app/Modules/AddOns/Services/AddonSnapshotService.php`
    - createSnapshot() - Capture addon data by category
- [ ] Create dealer pricing service
  - [ ] `app/Modules/Customers/Services/DealerPricingService.php`
    - calculatePrice($customer, $item, $type) - Apply brand/model/category discounts
    - Priority: variant-specific → model-specific → brand-specific → customer default
- [ ] Test snapshot creation
- [ ] Test dealer pricing calculations

#### **Day 39-40: Quote & Order Business Logic**
- [ ] Create DTOs
  - [ ] `app/Modules/Quotes/DTOs/QuoteData.php`
  - [ ] `app/Modules/Orders/DTOs/OrderData.php`
  - [ ] `app/Modules/Orders/DTOs/OrderItemData.php`
  - [ ] `app/Modules/Orders/DTOs/OrderSyncData.php`
- [ ] Create repositories
  - [ ] `app/Modules/Quotes/Repositories/QuoteRepository.php`
  - [ ] `app/Modules/Orders/Repositories/OrderRepository.php`
- [ ] Create services
  - [ ] `app/Modules/Quotes/Services/QuoteService.php`
  - [ ] `app/Modules/Quotes/Services/QuoteConversionService.php` (convert quote to order)
  - [ ] `app/Modules/Orders/Services/OrderService.php`
  - [ ] `app/Modules/Orders/Services/OrderFulfillmentService.php`
  - [ ] `app/Modules/Orders/Services/OrderSyncService.php` (from TunerStop/Wholesale)
- [ ] Create actions
  - [ ] `app/Modules/Quotes/Actions/CreateQuoteAction.php`
  - [ ] `app/Modules/Quotes/Actions/SendQuoteAction.php`
  - [ ] `app/Modules/Quotes/Actions/ConvertQuoteToOrderAction.php`
  - [ ] `app/Modules/Orders/Actions/CreateOrderAction.php`
  - [ ] `app/Modules/Orders/Actions/UpdateOrderStatusAction.php`
  - [ ] `app/Modules/Orders/Actions/AllocateInventoryAction.php`
  - [ ] `app/Modules/Orders/Actions/CancelOrderAction.php`
- [ ] Create events
  - [ ] `OrderCreated.php`
  - [ ] `OrderStatusChanged.php`
  - [ ] `OrderCancelled.php`
- [ ] Create listeners
  - [ ] `SendOrderConfirmation.php`
  - [ ] `UpdateInventoryOnOrder.php`
  - [ ] `NotifyWarehouse.php`

#### **Day 40-41: Admin Interface**
- [ ] Create Filament resource
  - [ ] `OrderResource.php`
  - Complex form with order items repeater
  - Customer selection with search
  - Product/variant selection
  - Pricing calculation
  - Warehouse allocation
- [ ] Create custom pages
  - [ ] Order view page with timeline
  - [ ] Order approval page
  - [ ] Shipping management page
- [ ] Create widgets
  - [ ] Orders by status
  - [ ] Revenue charts
  - [ ] Recent orders
- [ ] Add bulk actions
  - Bulk status update
  - Bulk export
  - Generate invoices

#### **Day 42: Order Sync & Testing**
- [ ] Create sync controller
  - [ ] `OrderSyncController.php`
    - POST `/api/sync/order`
    - Handle retail orders (TunerStop)
    - Handle wholesale orders
- [ ] Create sync job
  - [ ] `SyncOrderJob.php`
- [ ] Test order sync from both sources
- [ ] Write feature tests
  - [ ] `CreateOrderTest.php`
  - [ ] `OrderPricingTest.php`
  - [ ] `OrderSyncTest.php`
- [ ] Write unit tests
- [ ] Document order workflows

---

## 🔨 PHASE 3: Secondary Modules (Weeks 7-10)

### **Week 7: AddOns Module**

#### **Day 43-44: AddOns Setup**
- [ ] Create migrations
  - [ ] `create_addon_categories_table`
    - id, name, slug, description, required_attributes
  - [ ] `create_add_ons_table`
    - id, name, category_id, sku, attributes (JSONB), retail_price, wholesale_price, restock_notification_sent
- [ ] Create models
  - [ ] `AddOnCategory.php`
  - [ ] `AddOn.php`
- [ ] Seed categories
  - Wheel Accessories
  - Lug Nuts
  - Lug Bolts
  - Hub Rings
  - Spacers
  - TPMS
- [ ] Create services
  - [ ] `AddOnService.php`
  - [ ] `AddOnImportService.php` (CSV import by category)
- [ ] Create actions
  - [ ] `ImportAddOnsAction.php`
  - [ ] `UpdateAddOnQtyAction.php` (with restock notification)

#### **Day 45-46: Admin Interface**
- [ ] Create Filament resources
  - [ ] `AddOnCategoryResource.php`
  - [ ] `AddOnResource.php`
- [ ] Dynamic form fields based on category
- [ ] Create CSV import functionality
- [ ] Create restock notification system
- [ ] Test category-specific attributes

#### **Day 47-48: Consignment Module**
- [ ] Create migrations
  - [ ] `create_consignments_table`
    - id, customer_id, consignment_number, status, sent_date, delivered_date, notes
  - [ ] `create_consignment_items_table`
    - id, consignment_id, product_id, product_variant_id, addon_id, quantity_sent, quantity_sold, quantity_returned, is_hybrid
- [ ] Create models
  - [ ] `Consignment.php`
  - [ ] `ConsignmentItem.php`
- [ ] Create enum
  - [ ] `ConsignmentStatus.php` (Draft, Sent, Delivered, PartiallySold, Invoiced, Returned)
- [ ] Create service
  - [ ] `ConsignmentService.php`
- [ ] Create actions
  - [ ] `CreateConsignmentAction.php`
  - [ ] `MarkAsSentAction.php`
  - [ ] `RecordSaleAction.php`
  - [ ] `RecordReturnAction.php`

#### **Day 49: Consignment Admin**
- [ ] Create Filament resource
  - [ ] `ConsignmentResource.php`
  - Form with items repeater
  - Status workflow
- [ ] Create custom pages
  - Record sales page
  - Record returns page
  - Generate invoice page
- [ ] Test consignment workflow

---

### **Week 8: Invoices & Financial Transactions Module (UPDATED)**

#### **Day 50-51: Invoices & Payment Records Setup**
- [ ] Create migrations
  - [ ] `create_invoices_table` (NOTE: Also uses unified orders table with document_type='invoice')
    - id, invoice_number, customer_id, order_id, consignment_id, subtotal, tax_amount, total, amount_paid, balance_due, status, payment_status, **cost_of_goods, shipping_cost, duty_amount, delivery_fee, installation_cost, bank_fee, credit_card_fee, total_expenses, gross_profit, profit_margin**, wafeq_id, wafeq_sync_at
  - [ ] `create_invoice_items_table`
    - id, invoice_id, product_id, addon_id, product_snapshot (JSONB), product_name, sku, quantity, price, **tax_inclusive** (boolean), total_price
  - [ ] `create_payment_records_table` (NEW - replaces payments)
    - id, **payment_number**, order_id, invoice_id, customer_id, amount, payment_method, payment_date, transaction_id, gateway, notes, wafeq_id, wafeq_sync_at, recorded_by
  - [ ] `create_wafeq_sync_queue_table` (NEW)
    - id, entity_type, entity_id, wafeq_id, sync_status, sync_attempts, last_sync_attempt, last_error, payload (JSONB), response (JSONB)
  - [ ] `create_inventory_logs_table` (for consignment returns)
    - id, product_id, addon_id, variant_id, warehouse_id, type, quantity, previous_quantity, new_quantity, reference_type, reference_id, notes, created_by
- [ ] Create models
  - [ ] `app/Modules/Invoices/Models/Invoice.php`
  - [ ] `app/Modules/Invoices/Models/InvoiceItem.php`
  - [ ] `app/Modules/Invoices/Models/PaymentRecord.php` (with generatePaymentNumber())
  - [ ] `app/Modules/Invoices/Models/WafeqSyncQueue.php`
  - [ ] `app/Modules/Inventory/Models/InventoryLog.php`
- [ ] Create enums
  - [ ] `app/Modules/Invoices/Enums/InvoiceStatus.php`
  - [ ] `app/Modules/Invoices/Enums/PaymentMethod.php` (cash, card, bank_transfer, cheque, credit)
  - [ ] `app/Modules/Invoices/Enums/ExpenseType.php` (7 categories)

#### **Day 52-53: Financial Services & Wafeq Integration (CRITICAL)**
- [ ] Create DTOs
  - [ ] `app/Modules/Invoices/DTOs/InvoiceData.php`
  - [ ] `app/Modules/Invoices/DTOs/PaymentData.php`
  - [ ] `app/Modules/Invoices/DTOs/ExpenseData.php`
- [ ] Create Wafeq sync service
  - [ ] `app/Modules/Invoices/Services/WafeqSyncService.php`
    - syncPayment($payment) - Sync payment to Wafeq
    - syncExpenses($invoice) - Sync 7 expense categories
    - syncInvoice($invoice) - Sync complete invoice with line items
    - updateSyncQueue() - Track sync status
- [ ] Create invoice services
  - [ ] `app/Modules/Invoices/Services/InvoiceService.php`
    - recordPayment($amount, $method, ...) - Create PaymentRecord + update invoice + sync Wafeq
    - recordExpenses($expenseData) - Update 7 expense categories + auto-calculate profit
    - calculateProfit() - total_expenses, gross_profit, profit_margin
    - getExpenseSummary() - Return all expense categories
  - [ ] `app/Modules/Consignment/Services/ConsignmentService.php`
    - recordSale($soldItems) - Track items sold + create invoice
    - recordReturn($returnedItems) - Track items returned + log inventory
    - addBackToInventory() - Create InventoryLog entry
- [ ] Create queue jobs
  - [ ] `app/Modules/Invoices/Jobs/SyncPaymentToWafeq.php` (3 retries with backoff)
  - [ ] `app/Modules/Invoices/Jobs/SyncExpensesToWafeq.php`
  - [ ] `app/Modules/Invoices/Jobs/SyncInvoiceToWafeq.php`
- [ ] Create actions
  - [ ] `app/Modules/Invoices/Actions/RecordPaymentAction.php`
  - [ ] `app/Modules/Invoices/Actions/RecordExpensesAction.php`
  - [ ] `app/Modules/Consignment/Actions/RecordSaleAction.php`
  - [ ] `app/Modules/Consignment/Actions/RecordReturnAction.php`
- [ ] Create console command
  - [ ] `app/Console/Commands/RetryFailedWafeqSyncs.php`

#### **Day 54-55: Invoice Admin & Financial Operations**
- [ ] Create Filament resource
  - [ ] `InvoiceResource.php`
  - Invoice form
  - Items relation manager
  - Payments relation manager
  - Expenses relation manager
- [ ] Create custom pages
  - Invoice preview/print page
  - Payment recording page
  - Expense tracking page
- [ ] Create invoice PDF generator
- [ ] Add bulk actions
  - Bulk print
  - Bulk export
  - Bulk sync to Wafeq
- [ ] Test Wafeq integration

#### **Day 56: Testing**
- [ ] Write feature tests
  - [ ] `GenerateInvoiceTest.php`
  - [ ] `InvoiceCalculationTest.php`
  - [ ] `WafeqSyncTest.php`
- [ ] Write unit tests
- [ ] Test PDF generation
- [ ] Document invoice workflows

---

### **Week 9-10: Warranty Module**

#### **Day 57-58: Warranty Setup**
- [ ] Create migrations
  - [ ] `create_warranty_claims_table`
    - id, claim_number, customer_id, order_id, priority, status, issue_description, root_cause, resolution_type, resolution_description, sla_deadline, resolved_at, customer_satisfaction, total_cost
  - [ ] `create_warranty_claim_items_table`
    - id, claim_id, product_id, product_variant_id, quantity, issue_description
  - [ ] `create_warranty_costs_table`
    - id, claim_id, cost_type, amount, supplier_recoverable, description
  - [ ] `create_warranty_attachments_table`
    - id, claim_id, file_name, file_path, file_type
- [ ] Create models
  - [ ] `WarrantyClaim.php`
  - [ ] `WarrantyClaimItem.php`
  - [ ] `WarrantyCost.php`
  - [ ] `WarrantyAttachment.php`

#### **Day 59-60: Warranty Enums & Logic**
- [ ] Create enums
  - [ ] `ClaimStatus.php` (Draft, Submitted, UnderReview, Approved, Rejected, Resolved, Closed)
  - [ ] `ClaimPriority.php` (Low, Normal, High, Critical)
  - [ ] `ResolutionType.php` (Replacement, Refund, Repair, NoAction)
  - [ ] `CostType.php` (Shipping, Labor, Parts, Restocking, etc.)
- [ ] Create services
  - [ ] `WarrantyService.php`
  - [ ] `SLATrackingService.php`
    - Calculate SLA deadline based on priority
    - Auto-escalate overdue claims
    - Send notifications
- [ ] Create actions
  - [ ] `CreateClaimAction.php`
  - [ ] `EscalateClaimAction.php`
  - [ ] `ResolveClaimAction.php`
  - [ ] `RecordCostAction.php`
- [ ] Create events
  - [ ] `ClaimCreated.php`
  - [ ] `ClaimEscalated.php`
  - [ ] `ClaimResolved.php`
- [ ] Create listeners
  - [ ] `NotifyCustomerOfClaim.php`
  - [ ] `SendSLAWarning.php`

#### **Day 61-63: Warranty Admin**
- [ ] Create Filament resource
  - [ ] `WarrantyClaimResource.php`
  - Comprehensive form
  - Items relation manager
  - Costs relation manager
  - Attachments relation manager
  - Timeline component
- [ ] Create custom pages
  - Claim review page
  - Resolution page
  - Cost recovery page
- [ ] Create widgets
  - Claims by status
  - SLA compliance
  - Average resolution time
  - Cost analysis
- [ ] Create automated jobs
  - [ ] `CheckSLADeadlinesJob.php`
  - [ ] `SendSLANotificationsJob.php`
- [ ] Test SLA tracking
- [ ] Test attachment uploads

#### **Day 64-65: Testing & Analytics**
- [ ] Write feature tests
  - [ ] `CreateWarrantyClaimTest.php`
  - [ ] `SLATrackingTest.php`
  - [ ] `ClaimResolutionTest.php`
- [ ] Create warranty analytics
  - [ ] Claims by root cause
  - [ ] Claims by product/brand
  - [ ] Cost recovery rate
  - [ ] Customer satisfaction scores
- [ ] Create reports
  - Monthly warranty report
  - Cost analysis report
- [ ] Document warranty workflows

---

## 🎨 PHASE 4: Integration & Polish (Weeks 11-14)

### **Week 11: API Development**

#### **Day 66-67: RESTful API**
- [ ] Setup API versioning (`/api/v1/`)
- [ ] Setup Laravel Sanctum authentication
- [ ] Create API resources for all modules
  - [ ] `OrderResource.php`
  - [ ] `CustomerResource.php`
  - [ ] `ProductResource.php`
  - [ ] `InventoryResource.php`
  - [ ] `InvoiceResource.php`
- [ ] Create API controllers
  - [ ] `Api/V1/OrderController.php`
  - [ ] `Api/V1/CustomerController.php`
  - [ ] `Api/V1/ProductController.php`
- [ ] Setup rate limiting
- [ ] Add API authentication middleware

#### **Day 68-69: API Documentation**
- [ ] Install Scramble (API documentation generator)
  ```bash
  composer require dedoc/scramble
  ```
- [ ] Configure Scramble
- [ ] Add PHPDoc comments to all API endpoints
- [ ] Generate API documentation
- [ ] Create API usage guide
- [ ] Test all API endpoints with Postman
- [ ] Create Postman collection

#### **Day 70-72: Webhooks & External Integrations**
- [ ] Create webhook system
  - [ ] `WebhookService.php`
  - [ ] `WebhookQueue.php`
- [ ] Setup webhooks for:
  - Order created
  - Order status changed
  - Inventory updated
  - Invoice generated
- [ ] Create webhook configuration page in admin
- [ ] Test webhook delivery
- [ ] Add webhook retry logic
- [ ] Document webhook payloads

---

### **Week 12: Dashboard & Reporting**

#### **Day 73-74: Main Dashboard**
- [ ] Create Filament dashboard page
- [ ] Create widgets
  - [ ] Sales overview (today, week, month, year)
  - [ ] Revenue chart
  - [ ] Orders by status
  - [ ] Top selling products
  - [ ] Low stock alerts
  - [ ] Recent orders
  - [ ] Recent warranty claims
  - [ ] Pending invoices
- [ ] Add date range filters
- [ ] Add role-based widget visibility
- [ ] Test performance with large datasets

#### **Day 75-76: Reports Module**
- [ ] Create reports module structure
  - [ ] `app/Modules/Reports/`
- [ ] Create report classes
  - [ ] `SalesReport.php`
  - [ ] `InventoryReport.php`
  - [ ] `CustomerReport.php`
  - [ ] `ProductPerformanceReport.php`
  - [ ] `WarrantyReport.php`
- [ ] Create Filament pages for reports
  - [ ] Sales analytics
  - [ ] Inventory analytics
  - [ ] Customer analytics
  - [ ] Product performance
  - [ ] Warranty analytics
- [ ] Add export functionality (PDF, Excel, CSV)
- [ ] Create scheduled reports
  - Daily sales summary
  - Weekly inventory report
  - Monthly financial report

#### **Day 77-78: Notifications System**
- [ ] Setup Laravel notifications
- [ ] Create notification classes
  - [ ] `OrderCreatedNotification.php`
  - [ ] `LowStockNotification.php`
  - [ ] `WarrantyClaimNotification.php`
  - [ ] `InvoiceDueNotification.php`
- [ ] Setup notification channels
  - Database
  - Email
  - Slack (optional)
- [ ] Create Filament notification center
- [ ] Add notification preferences page
- [ ] Test notification delivery

---

### **Week 13: Performance Optimization**

#### **Day 79-80: Database Optimization**
- [ ] Add database indexes
  ```sql
  -- Products
  CREATE INDEX idx_products_brand ON products(brand_id);
  CREATE INDEX idx_products_status ON products(status) WHERE status = 'active';
  CREATE INDEX idx_products_search ON products USING GIN(search_vector);
  
  -- Orders
  CREATE INDEX idx_orders_customer ON orders(customer_id);
  CREATE INDEX idx_orders_status ON orders(order_status);
  CREATE INDEX idx_orders_created ON orders(created_at DESC);
  
  -- Inventory
  CREATE INDEX idx_inventory_product ON product_inventories(product_id, warehouse_id);
  CREATE INDEX idx_inventory_warehouse ON product_inventories(warehouse_id);
  ```
- [ ] Create materialized views for reports
  ```sql
  CREATE MATERIALIZED VIEW mv_daily_sales AS
  SELECT 
      date_trunc('day', created_at) as sale_date,
      COUNT(*) as order_count,
      SUM(total_amount) as total_sales
  FROM orders
  WHERE order_status = 'completed'
  GROUP BY date_trunc('day', created_at);
  
  CREATE INDEX idx_mv_daily_sales ON mv_daily_sales(sale_date);
  ```
- [ ] Setup query caching for expensive queries
- [ ] Optimize N+1 queries (add eager loading)
- [ ] Run database query analysis
  ```bash
  php artisan telescope:prune
  ```

#### **Day 81-82: Application Optimization**
- [ ] Enable OPcache
- [ ] Setup Redis caching
  - Cache frequently accessed data
  - Cache computed values (pricing, totals)
  - Cache search results
- [ ] Implement query result caching
  ```php
  $products = Cache::remember('products.active', 3600, function() {
      return Product::where('status', 'active')->get();
  });
  ```
- [ ] Add cache tags for easy invalidation
- [ ] Optimize Eloquent queries
- [ ] Implement lazy loading for images
- [ ] Add pagination to large lists
- [ ] Test performance with 100K+ products

#### **Day 83-84: Frontend Optimization**
- [ ] Optimize pqGrid loading
  - Virtual scrolling
  - Lazy loading
  - Pagination
- [ ] Minify CSS/JS assets
  ```bash
  npm run build
  ```
- [ ] Setup asset versioning
- [ ] Add lazy loading for images
- [ ] Optimize Livewire components
  - Use wire:poll sparingly
  - Implement wire:loading indicators
  - Optimize reactive properties
- [ ] Test grid performance with 100K rows

#### **Day 85: Monitoring Setup**
- [ ] Setup Laravel Pulse
  ```bash
  composer require laravel/pulse
  php artisan pulse:install
  php artisan migrate
  ```
- [ ] Configure Pulse dashboard
- [ ] Setup Sentry for error tracking
  ```bash
  composer require sentry/sentry-laravel
  ```
- [ ] Configure logging
  - Daily log rotation
  - Error notification
- [ ] Create health check endpoint
  ```php
  Route::get('/health', HealthCheckController::class);
  ```

---

### **Week 14: Security & Testing**

#### **Day 86-87: Security Hardening**
- [ ] Enable HTTPS only
- [ ] Configure CORS properly
- [ ] Add CSRF protection
- [ ] Setup rate limiting
  ```php
  // API routes
  Route::middleware(['throttle:60,1'])->group(function() {
      // API endpoints
  });
  ```
- [ ] Implement IP whitelisting for sync endpoints
- [ ] Add request logging
- [ ] Setup SQL injection prevention
- [ ] Add XSS protection
- [ ] Configure Content Security Policy
- [ ] Enable two-factor authentication (optional)
- [ ] Add audit logging for sensitive operations
- [ ] Test security vulnerabilities

#### **Day 88-89: Comprehensive Testing**
- [ ] Run full test suite
  ```bash
  php artisan test --coverage
  ```
- [ ] Feature tests coverage (target: 80%+)
- [ ] Unit tests coverage (target: 90%+)
- [ ] API endpoint tests
- [ ] Integration tests
  - Product sync flow
  - Order sync flow
  - Invoice generation flow
  - Warranty claim flow
- [ ] Browser tests with Laravel Dusk
  ```bash
  composer require --dev laravel/dusk
  php artisan dusk:install
  php artisan dusk
  ```
- [ ] Load testing
  - Use Laravel Octane (optional)
  - Stress test with 1000+ concurrent users
- [ ] Fix failing tests

#### **Day 90-91: User Acceptance Testing (UAT)**
- [ ] Create UAT test plan
- [ ] Setup staging environment
- [ ] Deploy to staging
- [ ] Create test data
- [ ] Conduct UAT with client
  - Orders module
  - Products & inventory
  - Customer management
  - Invoicing
  - Warranty claims
  - pqGrid functionality
  - Reports
- [ ] Document bugs/issues
- [ ] Fix critical bugs

#### **Day 92: Code Review & Documentation**
- [ ] Run static analysis
  ```bash
  ./vendor/bin/phpstan analyse
  ```
- [ ] Run code formatter
  ```bash
  ./vendor/bin/pint
  ```
- [ ] Code review checklist
  - Design patterns implemented correctly
  - SOLID principles followed
  - No code duplication
  - Proper error handling
  - Comments for complex logic
- [ ] Update documentation
  - Architecture overview
  - Module documentation
  - API documentation
  - Deployment guide
  - User manual

---

## 🚀 PHASE 5: Data Migration & Deployment (Weeks 15-16)

### **Week 15: Data Migration**

#### **Day 93-94: Migration Strategy**
- [ ] Analyze old database schema
- [ ] Create data mapping document
  - Old table → New table mappings
  - Field transformations
  - Data cleanup rules
- [ ] Create migration scripts
  - [ ] `MigrateCustomersCommand.php`
  - [ ] `MigrateDealersToCustomersCommand.php`
  - [ ] `MigrateProductsCommand.php`
  - [ ] `MigrateOrdersCommand.php`
  - [ ] `MigrateInventoryCommand.php`
  - [ ] `MigrateInvoicesCommand.php`
  - [ ] `MigrateWarrantyClaimsCommand.php`

#### **Day 95-96: Data Migration Execution**
- [ ] Backup old database
- [ ] Run migration scripts in test environment
  ```bash
  php artisan migrate:customers --dry-run
  php artisan migrate:customers
  ```
- [ ] Verify data integrity
  - Record counts match
  - Relationships preserved
  - No data loss
- [ ] Fix data issues
- [ ] Run final migration in staging
- [ ] Validate migrated data with client

#### **Day 97-98: Image Migration**
- [ ] Copy product images to S3
  ```bash
  php artisan migrate:images --source=old_uploads --bucket=reporting-crm
  ```
- [ ] Update image URLs in database
- [ ] Verify all images accessible
- [ ] Setup CloudFront CDN
- [ ] Test image loading performance

#### **Day 99: Final Data Validation**
- [ ] Run data validation scripts
  - Check for orphaned records
  - Verify foreign key relationships
  - Validate calculated fields
- [ ] Generate migration report
  - Total records migrated
  - Success rate
  - Failed records
  - Data discrepancies
- [ ] Get client sign-off on data

---

### **Week 16: Deployment & Launch**

#### **Day 100-101: Production Environment Setup**
- [ ] Provision production servers
  - Web server (nginx)
  - Application server (PHP-FPM)
  - Database server (PostgreSQL)
  - Redis server
  - Meilisearch server
- [ ] Setup load balancer (if needed)
- [ ] Configure SSL certificates (Let's Encrypt)
- [ ] Setup automated backups
  - Database backups (daily)
  - File backups (weekly)
  - Backup retention policy
- [ ] Configure monitoring
  - Uptime monitoring
  - Performance monitoring
  - Error tracking
- [ ] Setup log management
- [ ] Configure firewall rules

#### **Day 102-103: Deployment Process**
- [ ] Create deployment script
  ```bash
  # deploy.sh
  git pull origin main
  composer install --no-dev --optimize-autoloader
  npm run build
  php artisan migrate --force
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan queue:restart
  php artisan pulse:restart
  ```
- [ ] Deploy application to production
- [ ] Run post-deployment checks
  - Application loads correctly
  - Database connections work
  - Redis connections work
  - S3 uploads work
  - Email sending works
  - Queue workers running
- [ ] Configure CI/CD pipeline (GitHub Actions)
  ```yaml
  # .github/workflows/deploy.yml
  name: Deploy to Production
  on:
    push:
      branches: [main]
  jobs:
    deploy:
      runs-on: ubuntu-latest
      steps:
        - uses: actions/checkout@v2
        - name: Deploy
          run: |
            ssh user@server 'cd /var/www && ./deploy.sh'
  ```

#### **Day 104: Sync Integration Testing**
- [ ] Test product sync from TunerStop
  - Create test product in TunerStop
  - Verify sync to Reporting CRM
  - Check data accuracy
- [ ] Test order sync from TunerStop
  - Place test order
  - Verify sync
  - Check customer creation
  - Check inventory allocation
- [ ] Test order sync from Wholesale
  - Place test wholesale order
  - Verify sync
  - Check pricing rules applied
- [ ] Test Wafeq integration
  - Generate test invoice
  - Sync to Wafeq
  - Verify data in Wafeq
- [ ] Monitor sync logs

#### **Day 105: User Training**
- [ ] Prepare training materials
  - User manual
  - Video tutorials
  - Quick reference guides
- [ ] Conduct training sessions
  - Admin users
  - Sales team
  - Warehouse team
  - Customer service team
- [ ] Create FAQ document
- [ ] Setup support channel

#### **Day 106-107: Soft Launch**
- [ ] Soft launch with limited users
- [ ] Monitor system performance
  - Response times
  - Error rates
  - User feedback
- [ ] Fix critical issues immediately
- [ ] Optimize based on usage patterns
- [ ] Gather user feedback

#### **Day 108-109: Full Launch**
- [ ] Announce full launch
- [ ] Enable all users
- [ ] Monitor system closely
  - Server resources
  - Database performance
  - API response times
  - Queue processing
- [ ] Setup on-call rotation
- [ ] Create incident response plan
- [ ] Document known issues

#### **Day 110: Post-Launch Review**
- [ ] Review launch metrics
  - System uptime
  - User adoption
  - Performance metrics
  - Error rates
- [ ] Gather feedback from all stakeholders
- [ ] Create post-launch report
- [ ] Plan future improvements
- [ ] Schedule maintenance windows
- [ ] Celebrate success! 🎉

---

## 📊 Project Tracking

### **Milestones**

| Milestone | Target Date | Status |
|-----------|-------------|--------|
| Phase 1 Complete | Week 2 | ⏳ Pending |
| Core Modules Complete | Week 6 | ⏳ Pending |
| Secondary Modules Complete | Week 10 | ⏳ Pending |
| Integration Complete | Week 14 | ⏳ Pending |
| Data Migration Complete | Week 15 | ⏳ Pending |
| Production Launch | Week 16 | ⏳ Pending |

### **Risk Management**

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Data migration issues | Medium | High | Thorough testing in staging, maintain backups |
| Performance bottlenecks | Medium | Medium | Load testing, optimization in Phase 4 |
| Third-party API failures | Low | Medium | Implement retry logic, queue system |
| Scope creep | High | High | Strict change control, prioritize MVP |
| Team availability | Medium | High | Document everything, cross-train team |

### **Success Criteria**

✅ All 13 modules fully functional (added Quotes & Settings)
✅ pqGrid working with 100K+ rows  
✅ Snapshot-based product sync from TunerStop working (on-demand, not full catalog)
✅ Order sync from TunerStop & Wholesale working with document_type  
✅ Wafeq integration functional with queue-based sync
✅ Financial transactions recording (payment, expense, sale, return)
✅ Dealer pricing system working across all modules
✅ Tax inclusive/exclusive handling per item
✅ Settings module for system configuration
✅ Test coverage >80%  
✅ Page load time <2 seconds  
✅ API response time <500ms  
✅ Zero data loss during migration  
✅ User training completed  
✅ Client sign-off obtained  

---

## 🎯 KEY ARCHITECTURE UPDATES (October 20, 2025)

### **Critical Changes from Original Plan:**

**1. Laravel 12 (LTS) Instead of Laravel 11**
- Latest LTS version released March 2024
- 2 years of bug fixes + 3 years of security updates
- Better performance and new features

**2. Unified Orders Table Approach**
- ❌ OLD: Separate tables for quotes, orders, invoices
- ✅ NEW: Single `orders` table with `document_type` ENUM
- Benefits: Simplified schema, easier conversions, less duplication

**3. Snapshot-Based Product Sync**
- ❌ OLD: Full product catalog sync from TunerStop
- ✅ NEW: On-demand sync with JSONB snapshots at order time
- Captures product/variant data at moment of order
- No dependency on external system for historical orders

**4. Separate Quotes Module**
- NEW module with own lifecycle (Draft → Sent → Approved → Converted)
- Convert quotes to orders seamlessly
- Separate permissions and workflows

**5. Settings Module**
- NEW module for system-wide configuration
- Tax rates, currency, company branding
- Document templates (invoice, quote, consignment)
- Integration settings (Wafeq, TunerStop APIs)

**6. Wafeq Accounting Integration**
- Queue-based sync for payments, expenses, invoices
- Automatic retry with exponential backoff
- Sync queue table for tracking failures
- Console command to retry failed syncs

**7. Financial Transaction Recording**
- **Record Payment**: PaymentRecord model with auto-generated numbers (PAY-20251020-0001)
- **Record Expenses**: 7 expense categories with auto profit calculation
- **Record Sale**: Consignment items sold, auto-create invoice
- **Record Return**: Consignment items returned, log inventory

**8. Dealer Pricing System**
- DealerPricingService used across ALL modules
- Priority: variant → model → brand → customer default
- Activates when customer_type = 'dealer'

**9. Tax Handling per Item**
- `tax_inclusive` boolean on every order_item, invoice_item, consignment_item
- Mixed tax calculation support
- Configurable via Settings module

**10. Reference-Only Inventory**
- External warehouse/ERP system is source of truth
- This system stores inventory for display only
- Consignment returns logged but external system handles actual inventory

### **Module Count Update:**
- Original Plan: 10 modules
- Updated Plan: 13 modules
  1. Quotes (NEW)
  2. Orders
  3. Customers
  4. Products
  5. Variants
  6. Inventory
  7. AddOns
  8. Warehouse (explicit)
  9. Consignment
  10. Invoices (enhanced with financial transactions)
  11. Warranty
  12. Sync
  13. Settings (NEW)

### **Database Schema Impact:**
- Added `document_type` to orders table
- Added `product_snapshot` and `variant_snapshot` JSONB columns
- Added `tax_inclusive` boolean to all item tables
- Added `payment_records` table (replacing simple payments)
- Added `wafeq_sync_queue` table
- Added `inventory_logs` table
- Added `settings`, `company_branding`, `document_templates` tables
- Added 7 expense columns to invoices table
- Added `brand_id`, `model_id` FKs for dealer pricing

---

## 📝 Notes & Best Practices

### **Development Workflow**
1. Create feature branch from `develop`
2. Write tests first (TDD)
3. Implement feature
4. Run tests + static analysis
5. Create pull request
6. Code review
7. Merge to `develop`
8. Deploy to staging
9. QA testing
10. Merge to `main` for production

### **Git Branch Strategy**
```
main (production)
├── develop (staging)
│   ├── feature/customers-module
│   ├── feature/products-module
│   ├── feature/orders-module
│   └── bugfix/inventory-allocation
```

### **Code Review Checklist**
- [ ] Follows design patterns
- [ ] SOLID principles applied
- [ ] No code duplication
- [ ] Proper error handling
- [ ] Security considerations
- [ ] Performance optimized
- [ ] Tests included
- [ ] Documentation updated

### **Daily Standup Questions**
1. What did I complete yesterday?
2. What will I work on today?
3. Any blockers?

---

**END OF IMPLEMENTATION PLAN**

Ready to build! 🚀
