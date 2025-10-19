# New Reporting CRM - System Architecture
## Modular, Scalable, Clean Architecture with Best Practices

**Project:** Reporting CRM v2.0 (Clean Rebuild)  
**Architecture:** Modular Monolith with Clean Architecture Principles  
**Date:** October 20, 2025  
**Status:** Planning Phase  

---

## 🎯 Executive Summary

Building a **clean, modular, scalable CRM** to replace the messy existing system while maintaining all functionality and client requirements (Excel-based grids).

**Key Decisions:**
- ✅ **Framework:** Laravel 12 (PHP 8.3+) - Latest LTS
- ✅ **Admin Panel:** Filament v3
- ✅ **Excel Grids:** ParamQuery Grid (pqGrid Pro)
- ✅ **Database:** PostgreSQL 15
- ✅ **Architecture:** Clean Architecture + Domain-Driven Design (DDD)
- ✅ **Frontend:** Livewire 3 + Alpine.js 3 + pqGrid
- ✅ **API:** RESTful API with Laravel Sanctum

---

## 🏗️ System Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    NEW REPORTING CRM v2.0                   │
│                  (Clean Modular Architecture)                │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  PRESENTATION LAYER                                          │
├─────────────────────────────────────────────────────────────┤
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │  Filament  │  │  Livewire  │  │   pqGrid   │            │
│  │  Admin UI  │  │  Components│  │  (Excel)   │            │
│  └────────────┘  └────────────┘  └────────────┘            │
│                                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │  REST API  │  │  Webhooks  │  │   Events   │            │
│  │ (Sanctum)  │  │  (External)│  │ (Internal) │            │
│  └────────────┘  └────────────┘  └────────────┘            │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  APPLICATION LAYER                                           │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────┐   │
│  │  MODULES (Bounded Contexts)                         │   │
│  │                                                      │   │
│  │  [Orders] [Customers] [Products] [Inventory]        │   │
│  │  [Variants] [AddOns] [Consignment] [Invoices]       │   │
│  │  [Warranty] [Sync] [Warehouse] [Reporting]          │   │
│  │                                                      │   │
│  │  Each Module Contains:                              │   │
│  │  - Domain Models                                    │   │
│  │  - Actions (Use Cases)                              │   │
│  │  - DTOs (Data Transfer Objects)                     │   │
│  │  - Services (Business Logic)                        │   │
│  │  - Repositories (Data Access)                       │   │
│  │  - Events & Listeners                               │   │
│  │  - Policies (Authorization)                         │   │
│  │  - Resources (API)                                  │   │
│  │  - Jobs (Background Tasks)                          │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  DOMAIN LAYER                                                │
├─────────────────────────────────────────────────────────────┤
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │   Models   │  │   Enums    │  │   Value    │            │
│  │ (Eloquent) │  │  (Status)  │  │  Objects   │            │
│  └────────────┘  └────────────┘  └────────────┘            │
│                                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │ Business   │  │   Domain   │  │  Contracts │            │
│  │   Rules    │  │   Events   │  │(Interfaces)│            │
│  └────────────┘  └────────────┘  └────────────┘            │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│  INFRASTRUCTURE LAYER                                        │
├─────────────────────────────────────────────────────────────┤
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │ PostgreSQL │  │   Redis    │  │     S3     │            │
│  │ (Database) │  │(Cache/Queue)│ │ (Storage)  │            │
│  └────────────┘  └────────────┘  └────────────┘            │
│                                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐            │
│  │Meilisearch │  │  External  │  │  Wafeq     │            │
│  │  (Search)  │  │    APIs    │  │Accounting) │            │
│  └────────────┘  └────────────┘  └────────────┘            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  EXTERNAL SYSTEMS                                            │
├─────────────────────────────────────────────────────────────┤
│  [TunerStop Admin] → Products/Orders Sync                   │
│  [Wholesale Admin] → Orders Sync                            │
│  [Wafeq] → Invoices Sync                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 Technology Stack (Detailed)

### **Backend Framework**
```yaml
Framework: Laravel 11.x
PHP Version: 8.3+
Architecture: Modular Monolith
Design Patterns:
  - Repository Pattern
  - Service Layer Pattern
  - Action Pattern (Single Responsibility)
  - Strategy Pattern (Payment/Pricing)
  - Observer Pattern (Events)
  - Factory Pattern (DTOs)
  - Adapter Pattern (External APIs)
```

### **Admin Panel**
```yaml
UI Framework: Filament v3
Features:
  - Resource Management (CRUD)
  - Custom Pages
  - Widgets & Dashboard
  - Relation Managers
  - Actions & Bulk Actions
  - Import/Export
  - Notifications
  - Dark Mode
Styling: Tailwind CSS 3
Icons: Heroicons
```

### **Excel Grid Component**
```yaml
Grid Library: ParamQuery Grid Pro 9.x
License: Commercial (Client has license)
Features:
  - 100,000+ rows support
  - Excel-like editing
  - Copy/Paste from Excel
  - Autofill & drag-to-fill
  - Frozen rows/columns
  - Inline editing
  - Batch updates
  - Export to Excel
Integration: Livewire components wrapper
Used In: Products, Inventory, Variants, AddOns grids
```

### **Database**
```yaml
RDBMS: PostgreSQL 15
Features Used:
  - JSONB columns (for flexible attributes)
  - Full-text search
  - Partial indexes
  - Generated columns
  - Row-level security (future)
  - Materialized views (reporting)
Migration: Laravel Migrations
Seeding: Laravel Seeders with Factories
```

### **Cache & Queue**
```yaml
Cache: Redis 7.x
Queue: Redis + Laravel Horizon
Session: Redis
Broadcast: Redis (Laravel Echo)
```

### **Search**
```yaml
Engine: Meilisearch 1.5
Features:
  - Typo tolerance
  - Faceted search
  - Filtering
  - Sorting
  - Instant search
Integration: Laravel Scout
Indexed Models: Products, Customers, Orders
```

### **Storage**
```yaml
Primary: AWS S3
Driver: Laravel Storage (Flysystem)
CDN: CloudFront (for images)
Local: For development
Uploads: Product images, documents, attachments
```

### **API**
```yaml
Type: RESTful API
Authentication: Laravel Sanctum (Token-based)
Documentation: Scramble (Auto-generated)
Versioning: URL versioning (/api/v1/...)
Rate Limiting: Built-in Laravel
```

### **Frontend**
```yaml
Admin: 
  - Filament v3 (Livewire components)
  - Alpine.js 3
  - pqGrid Pro 9.x
Custom UI:
  - Livewire 3
  - Alpine.js 3
  - Tailwind CSS 3
Build Tool: Vite 5
```

### **Testing**
```yaml
Framework: Pest PHP 2.x
Types:
  - Feature Tests
  - Unit Tests
  - Integration Tests
  - API Tests
Coverage: PHPUnit Code Coverage
CI/CD: GitHub Actions
```

### **Code Quality**
```yaml
Formatter: Laravel Pint
Static Analysis: PHPStan (Level 8)
Git Hooks: Husky + lint-staged
Standards: PSR-12
```

### **Monitoring & Debugging**
```yaml
Development:
  - Laravel Telescope
  - Laravel Debugbar
  - Clockwork
Production:
  - Laravel Pulse
  - Sentry (Error tracking)
  - New Relic (APM)
Logs: Laravel Log (Monolog)
```

### **DevOps**
```yaml
Version Control: Git (GitHub)
CI/CD: GitHub Actions
Containerization: Docker + Docker Compose
Server: Ubuntu 22.04 LTS
Web Server: Nginx
PHP: PHP-FPM 8.3
Deployment: Laravel Forge or Envoyer
```

---

## 🗂️ Project Structure (Modular Architecture)

```
reporting-crm/
│
├── app/
│   ├── Core/                          # Core domain logic
│   │   ├── Contracts/                 # Interfaces
│   │   ├── Enums/                     # Enumerations
│   │   ├── Exceptions/                # Custom exceptions
│   │   ├── Traits/                    # Reusable traits
│   │   └── ValueObjects/              # Value objects
│   │
│   ├── Modules/                       # Bounded Contexts (DDD)
│   │   │
│   │   ├── Orders/
│   │   │   ├── Actions/               # Use cases
│   │   │   │   ├── CreateOrderAction.php
│   │   │   │   ├── UpdateOrderStatusAction.php
│   │   │   │   ├── GenerateInvoiceAction.php
│   │   │   │   └── AllocateInventoryAction.php
│   │   │   │
│   │   │   ├── DTOs/                  # Data Transfer Objects
│   │   │   │   ├── OrderData.php
│   │   │   │   ├── OrderItemData.php
│   │   │   │   └── OrderSyncData.php
│   │   │   │
│   │   │   ├── Enums/
│   │   │   │   ├── OrderStatus.php
│   │   │   │   ├── OrderType.php
│   │   │   │   └── PaymentStatus.php
│   │   │   │
│   │   │   ├── Events/
│   │   │   │   ├── OrderCreated.php
│   │   │   │   ├── OrderStatusChanged.php
│   │   │   │   └── OrderCancelled.php
│   │   │   │
│   │   │   ├── Listeners/
│   │   │   │   ├── SendOrderConfirmation.php
│   │   │   │   ├── UpdateInventoryOnOrder.php
│   │   │   │   └── NotifyWarehouse.php
│   │   │   │
│   │   │   ├── Models/
│   │   │   │   ├── Order.php
│   │   │   │   ├── OrderItem.php
│   │   │   │   └── OrderItemQuantity.php
│   │   │   │
│   │   │   ├── Policies/
│   │   │   │   └── OrderPolicy.php
│   │   │   │
│   │   │   ├── Repositories/          # Data access layer
│   │   │   │   ├── Contracts/
│   │   │   │   │   └── OrderRepositoryInterface.php
│   │   │   │   └── OrderRepository.php
│   │   │   │
│   │   │   ├── Services/              # Business logic
│   │   │   │   ├── OrderService.php
│   │   │   │   ├── OrderSyncService.php
│   │   │   │   ├── OrderPricingService.php
│   │   │   │   └── OrderFulfillmentService.php
│   │   │   │
│   │   │   ├── Resources/             # API Resources
│   │   │   │   ├── OrderResource.php
│   │   │   │   └── OrderCollection.php
│   │   │   │
│   │   │   ├── Filament/              # Filament Admin
│   │   │   │   ├── Resources/
│   │   │   │   │   └── OrderResource.php
│   │   │   │   └── Widgets/
│   │   │   │       └── OrderStatsWidget.php
│   │   │   │
│   │   │   ├── Jobs/                  # Background jobs
│   │   │   │   ├── SyncOrderJob.php
│   │   │   │   └── GenerateInvoiceJob.php
│   │   │   │
│   │   │   └── Observers/
│   │   │       └── OrderObserver.php
│   │   │
│   │   ├── Customers/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateCustomerAction.php
│   │   │   │   ├── UpdateCustomerAction.php
│   │   │   │   └── ApplyPricingRulesAction.php
│   │   │   ├── DTOs/
│   │   │   │   ├── CustomerData.php
│   │   │   │   └── AddressData.php
│   │   │   ├── Enums/
│   │   │   │   └── CustomerType.php
│   │   │   ├── Models/
│   │   │   │   ├── Customer.php
│   │   │   │   ├── AddressBook.php
│   │   │   │   ├── CustomerBrandPricing.php
│   │   │   │   ├── CustomerModelPricing.php
│   │   │   │   └── CustomerAddonCategoryPricing.php
│   │   │   ├── Services/
│   │   │   │   ├── CustomerService.php
│   │   │   │   └── CustomerPricingService.php
│   │   │   ├── Repositories/
│   │   │   │   └── CustomerRepository.php
│   │   │   ├── Filament/
│   │   │   │   └── Resources/
│   │   │   │       └── CustomerResource.php
│   │   │   └── Policies/
│   │   │       └── CustomerPolicy.php
│   │   │
│   │   ├── Products/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProductAction.php
│   │   │   │   ├── SyncProductAction.php
│   │   │   │   ├── UpdateProductAction.php
│   │   │   │   └── DeleteProductAction.php
│   │   │   ├── DTOs/
│   │   │   │   ├── ProductData.php
│   │   │   │   └── ProductSyncData.php
│   │   │   ├── Models/
│   │   │   │   ├── Product.php
│   │   │   │   ├── Brand.php
│   │   │   │   ├── VehicleModel.php
│   │   │   │   ├── Finish.php
│   │   │   │   └── ProductImage.php
│   │   │   ├── Services/
│   │   │   │   ├── ProductService.php
│   │   │   │   ├── ProductSyncService.php
│   │   │   │   ├── BrandMappingService.php
│   │   │   │   ├── ModelMappingService.php
│   │   │   │   └── FinishMappingService.php
│   │   │   ├── Filament/
│   │   │   │   └── Resources/
│   │   │   │       ├── ProductResource.php
│   │   │   │       └── ProductResource/
│   │   │   │           └── Pages/
│   │   │   │               └── ProductGridPage.php (pqGrid)
│   │   │   ├── Livewire/              # pqGrid components
│   │   │   │   ├── ProductsGrid.php
│   │   │   │   └── ProductVariantsGrid.php
│   │   │   └── Jobs/
│   │   │       └── SyncProductJob.php
│   │   │
│   │   ├── Variants/
│   │   │   ├── Models/
│   │   │   │   └── ProductVariant.php
│   │   │   ├── Services/
│   │   │   │   └── VariantService.php
│   │   │   ├── Actions/
│   │   │   │   ├── ImportVariantsAction.php
│   │   │   │   └── UpdateVariantAction.php
│   │   │   └── Livewire/
│   │   │       └── VariantsGrid.php (pqGrid)
│   │   │
│   │   ├── Inventory/
│   │   │   ├── Actions/
│   │   │   │   ├── UpdateInventoryAction.php
│   │   │   │   ├── AllocateInventoryAction.php
│   │   │   │   └── TransferInventoryAction.php
│   │   │   ├── Models/
│   │   │   │   ├── ProductInventory.php
│   │   │   │   └── Warehouse.php
│   │   │   ├── Services/
│   │   │   │   ├── InventoryService.php
│   │   │   │   └── WarehouseFulfillmentService.php
│   │   │   ├── Filament/
│   │   │   │   └── Resources/
│   │   │   │       └── InventoryResource.php
│   │   │   └── Livewire/
│   │   │       └── InventoryGrid.php (pqGrid)
│   │   │
│   │   ├── AddOns/
│   │   │   ├── Models/
│   │   │   │   ├── AddOn.php
│   │   │   │   └── AddOnCategory.php
│   │   │   ├── Services/
│   │   │   │   ├── AddOnService.php
│   │   │   │   └── AddOnImportService.php
│   │   │   ├── Actions/
│   │   │   │   ├── ImportAddOnsAction.php
│   │   │   │   └── UpdateAddOnQtyAction.php
│   │   │   └── Filament/
│   │   │       └── Resources/
│   │   │           └── AddOnResource.php
│   │   │
│   │   ├── Consignment/
│   │   │   ├── Models/
│   │   │   │   ├── Consignment.php
│   │   │   │   └── ConsignmentItem.php
│   │   │   ├── Enums/
│   │   │   │   └── ConsignmentStatus.php
│   │   │   ├── Services/
│   │   │   │   └── ConsignmentService.php
│   │   │   ├── Actions/
│   │   │   │   ├── CreateConsignmentAction.php
│   │   │   │   ├── MarkAsSentAction.php
│   │   │   │   ├── RecordSaleAction.php
│   │   │   │   └── RecordReturnAction.php
│   │   │   └── Filament/
│   │   │       └── Resources/
│   │   │           └── ConsignmentResource.php
│   │   │
│   │   ├── Invoices/
│   │   │   ├── Models/
│   │   │   │   ├── Invoice.php
│   │   │   │   ├── InvoiceItem.php
│   │   │   │   └── Payment.php
│   │   │   ├── Enums/
│   │   │   │   ├── InvoiceStatus.php
│   │   │   │   └── PaymentMethod.php
│   │   │   ├── Services/
│   │   │   │   ├── InvoiceService.php
│   │   │   │   ├── WafeqService.php
│   │   │   │   └── InvoiceCalculationService.php
│   │   │   ├── Actions/
│   │   │   │   ├── GenerateInvoiceAction.php
│   │   │   │   ├── SyncToWafeqAction.php
│   │   │   │   └── RecordPaymentAction.php
│   │   │   └── Filament/
│   │   │       └── Resources/
│   │   │           └── InvoiceResource.php
│   │   │
│   │   ├── Warranty/
│   │   │   ├── Models/
│   │   │   │   ├── WarrantyClaim.php
│   │   │   │   └── WarrantyClaimItem.php
│   │   │   ├── Enums/
│   │   │   │   ├── ClaimStatus.php
│   │   │   │   ├── ClaimPriority.php
│   │   │   │   └── ResolutionType.php
│   │   │   ├── Services/
│   │   │   │   ├── WarrantyService.php
│   │   │   │   └── SLATrackingService.php
│   │   │   ├── Actions/
│   │   │   │   ├── CreateClaimAction.php
│   │   │   │   ├── EscalateClaimAction.php
│   │   │   │   └── ResolveClaimAction.php
│   │   │   └── Filament/
│   │   │       └── Resources/
│   │   │           └── WarrantyClaimResource.php
│   │   │
│   │   └── Sync/                      # Sync module
│   │       ├── Models/
│   │       │   └── SyncOperation.php
│   │       ├── Services/
│   │       │   ├── SyncService.php
│   │       │   └── SyncRetryService.php
│   │       ├── Jobs/
│   │       │   ├── ProcessSyncQueueJob.php
│   │       │   └── RetryFailedSyncJob.php
│   │       └── Http/
│   │           └── Controllers/
│   │               ├── ProductSyncController.php
│   │               └── OrderSyncController.php
│   │
│   ├── Shared/                        # Shared across modules
│   │   ├── Services/
│   │   │   ├── GeolocationService.php
│   │   │   ├── ImageService.php
│   │   │   └── NotificationService.php
│   │   ├── Traits/
│   │   │   ├── HasAddresses.php
│   │   │   ├── HasAuditLog.php
│   │   │   └── HasUuid.php
│   │   └── Utilities/
│   │       ├── PriceCalculator.php
│   │       └── DateHelper.php
│   │
│   └── Http/
│       ├── Controllers/
│       │   └── Api/
│       │       └── V1/
│       │           ├── OrderController.php
│       │           ├── ProductController.php
│       │           └── CustomerController.php
│       └── Middleware/
│           ├── CheckApiToken.php
│           └── LogApiRequests.php
│
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   │   ├── 2025_01_01_000001_create_customers_table.php
│   │   ├── 2025_01_01_000002_create_products_table.php
│   │   └── ...
│   └── seeders/
│
├── public/
│   ├── pqgrid/                        # pqGrid library
│   │   ├── pqgrid.min.js
│   │   ├── pqgrid.min.css
│   │   └── themes/
│   └── assets/
│
├── resources/
│   ├── views/
│   │   ├── livewire/
│   │   │   ├── products-grid.blade.php
│   │   │   ├── inventory-grid.blade.php
│   │   │   └── variants-grid.blade.php
│   │   └── components/
│   ├── css/
│   └── js/
│       ├── app.js
│       └── grid-helpers.js            # pqGrid utilities
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── console.php
│
├── storage/
├── tests/
│   ├── Feature/
│   │   ├── Orders/
│   │   ├── Products/
│   │   └── Customers/
│   └── Unit/
│       ├── Services/
│       └── Actions/
│
├── .env.example
├── artisan
├── composer.json
├── docker-compose.yml
├── Dockerfile
├── package.json
├── phpstan.neon
├── phpunit.xml
├── pint.json
└── README.md
```

---

## 🎨 Design Patterns Used

### 1. **Repository Pattern**
```php
// Interface
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
    public function getByStatus(OrderStatus $status): Collection;
}

// Implementation
class OrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id): ?Order
    {
        return Order::with(['items', 'customer'])->find($id);
    }
    
    public function create(array $data): Order
    {
        return Order::create($data);
    }
    
    // ... more methods
}
```

### 2. **Action Pattern (Single Responsibility)**
```php
class CreateOrderAction
{
    public function __construct(
        private OrderRepository $orderRepository,
        private InventoryService $inventoryService,
        private CustomerPricingService $pricingService
    ) {}
    
    public function execute(OrderData $data): Order
    {
        DB::beginTransaction();
        
        try {
            // Create order
            $order = $this->orderRepository->create($data->toArray());
            
            // Apply pricing rules
            $this->pricingService->applyRules($order);
            
            // Allocate inventory
            $this->inventoryService->allocate($order);
            
            // Fire event
            event(new OrderCreated($order));
            
            DB::commit();
            return $order;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

### 3. **DTO Pattern (Data Transfer Objects)**
```php
class OrderData
{
    public function __construct(
        public readonly int $customer_id,
        public readonly OrderType $order_type,
        public readonly OrderStatus $status,
        public readonly array $items,
        public readonly ?string $notes = null
    ) {}
    
    public static function fromRequest(Request $request): self
    {
        return new self(
            customer_id: $request->integer('customer_id'),
            order_type: OrderType::from($request->string('order_type')),
            status: OrderStatus::from($request->string('status')),
            items: $request->array('items'),
            notes: $request->string('notes')
        );
    }
    
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customer_id,
            'order_type' => $this->order_type->value,
            'status' => $this->status->value,
            'notes' => $this->notes,
        ];
    }
}
```

### 4. **Strategy Pattern (Pricing)**
```php
interface PricingStrategyInterface
{
    public function calculate(Order $order): float;
}

class RetailPricingStrategy implements PricingStrategyInterface
{
    public function calculate(Order $order): float
    {
        return $order->items->sum(fn($item) => $item->retail_price * $item->quantity);
    }
}

class WholesalePricingStrategy implements PricingStrategyInterface
{
    public function calculate(Order $order): float
    {
        $customer = $order->customer;
        $total = 0;
        
        foreach ($order->items as $item) {
            $price = $this->applyDiscounts($item, $customer);
            $total += $price * $item->quantity;
        }
        
        return $total;
    }
    
    private function applyDiscounts($item, $customer): float
    {
        // Apply brand discount
        // Apply model discount
        // Apply category discount
        return $price;
    }
}

// Usage
class OrderPricingService
{
    public function calculateTotal(Order $order): float
    {
        $strategy = match($order->order_type) {
            OrderType::Retail => new RetailPricingStrategy(),
            OrderType::Wholesale => new WholesalePricingStrategy(),
            OrderType::B2B => new B2BPricingStrategy(),
        };
        
        return $strategy->calculate($order);
    }
}
```

### 5. **Observer Pattern (Events)**
```php
class Order extends Model
{
    protected $dispatchesEvents = [
        'created' => OrderCreated::class,
        'updated' => OrderUpdated::class,
        'deleted' => OrderDeleted::class,
    ];
}

// Event
class OrderCreated
{
    public function __construct(public Order $order) {}
}

// Listener
class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        
        Mail::to($order->customer->email)
            ->send(new OrderConfirmationMail($order));
    }
}

// Register in EventServiceProvider
protected $listen = [
    OrderCreated::class => [
        SendOrderConfirmation::class,
        UpdateInventoryOnOrder::class,
        NotifyWarehouse::class,
    ],
];
```

### 6. **Factory Pattern**
```php
class ProductFactory
{
    public static function createFromSyncData(ProductSyncData $data): Product
    {
        return Product::create([
            'name' => $data->name,
            'slug' => Str::slug($data->name),
            'brand_id' => BrandMappingService::map($data->brand_id),
            'model_id' => ModelMappingService::map($data->model_id),
            'finish_id' => FinishMappingService::map($data->finish_id),
            'description' => $data->description,
            'retail_price' => $data->retail_price,
        ]);
    }
}
```

### 7. **Adapter Pattern (External APIs)**
```php
interface AccountingServiceInterface
{
    public function syncInvoice(Invoice $invoice): bool;
    public function getInvoiceStatus(string $external_id): string;
}

class WafeqAdapter implements AccountingServiceInterface
{
    public function syncInvoice(Invoice $invoice): bool
    {
        $response = Http::post('https://api.wafeq.com/invoices', [
            'customer_name' => $invoice->customer->name,
            'items' => $this->transformItems($invoice->items),
            'total' => $invoice->total_amount,
        ]);
        
        return $response->successful();
    }
    
    private function transformItems($items): array
    {
        // Transform to Wafeq format
        return $items->map(fn($item) => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'price' => $item->unit_price,
        ])->toArray();
    }
}
```

---

## 🔌 pqGrid Integration Strategy

### **Livewire Component Wrapper**

```php
<?php

namespace App\Modules\Products\Livewire;

use Livewire\Component;
use App\Modules\Products\Models\Product;

class ProductsGrid extends Component
{
    public $gridData;
    public $filters = [];
    
    public function mount()
    {
        $this->loadGridData();
    }
    
    public function loadGridData()
    {
        $query = Product::with(['brand', 'model', 'finish', 'variants']);
        
        // Apply filters
        if (!empty($this->filters['brand_id'])) {
            $query->where('brand_id', $this->filters['brand_id']);
        }
        
        $this->gridData = $query->get()->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand->name,
                'model' => $product->model->name,
                'finish' => $product->finish->name,
                'retail_price' => $product->retail_price,
                'wholesale_price' => $product->wholesale_price,
                'stock' => $product->total_inventory,
                'status' => $product->status->value,
            ];
        })->toArray();
    }
    
    public function updateCell($rowId, $column, $value)
    {
        $product = Product::findOrFail($rowId);
        $product->update([$column => $value]);
        
        $this->dispatch('cell-updated', [
            'message' => 'Product updated successfully',
        ]);
    }
    
    public function bulkUpdate($updates)
    {
        DB::transaction(function() use ($updates) {
            foreach ($updates as $update) {
                Product::where('id', $update['id'])
                    ->update($update['changes']);
            }
        });
        
        $this->loadGridData();
        $this->dispatch('bulk-update-complete');
    }
    
    public function exportToExcel()
    {
        return Excel::download(
            new ProductsExport($this->filters),
            'products.xlsx'
        );
    }
    
    public function render()
    {
        return view('livewire.products-grid');
    }
}
```

### **Blade Template**

```html
<!-- resources/views/livewire/products-grid.blade.php -->
<div>
    <!-- Filters -->
    <div class="mb-4">
        <select wire:model.live="filters.brand_id" class="form-select">
            <option value="">All Brands</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
        
        <button wire:click="exportToExcel" class="btn btn-primary">
            Export to Excel
        </button>
    </div>
    
    <!-- pqGrid Container -->
    <div id="products-grid"></div>
    
    @push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            let grid;
            let gridData = @json($gridData);
            
            const colModel = [
                { title: "ID", dataIndx: "id", width: 80, editable: false },
                { title: "Product Name", dataIndx: "name", width: 250, editable: true },
                { title: "SKU", dataIndx: "sku", width: 120, editable: true },
                { title: "Brand", dataIndx: "brand", width: 150, editable: false },
                { title: "Model", dataIndx: "model", width: 150, editable: false },
                { title: "Finish", dataIndx: "finish", width: 120, editable: false },
                { 
                    title: "Retail Price", 
                    dataIndx: "retail_price", 
                    width: 120, 
                    editable: true,
                    dataType: "float",
                    format: "$#,###.00"
                },
                { 
                    title: "Wholesale Price", 
                    dataIndx: "wholesale_price", 
                    width: 120, 
                    editable: true,
                    dataType: "float",
                    format: "$#,###.00"
                },
                { title: "Stock", dataIndx: "stock", width: 100, editable: false },
                { 
                    title: "Status", 
                    dataIndx: "status", 
                    width: 120, 
                    editable: true,
                    editor: {
                        type: 'select',
                        options: ['active', 'inactive', 'discontinued']
                    }
                }
            ];
            
            const gridOptions = {
                width: '100%',
                height: 600,
                colModel: colModel,
                dataModel: {
                    data: gridData,
                    location: "local"
                },
                editable: true,
                editMode: 'cell',
                scrollModel: { autoFit: true },
                selectionModel: { type: 'row', mode: 'range' },
                numberCell: { show: true },
                title: "Products",
                pageModel: { type: "local", rPP: 100 },
                filterModel: { header: true },
                historyModel: { on: true }, // Undo/Redo
                
                // Excel-like features
                excel: {
                    on: true,
                    all: true
                },
                copyModel: {
                    on: true,
                    render: true
                },
                fillHandle: 'all', // Autofill like Excel
                
                // Events
                cellSave: function(event, ui) {
                    const rowData = ui.rowData;
                    const column = ui.dataIndx;
                    const value = ui.newVal;
                    
                    // Call Livewire method
                    @this.updateCell(rowData.id, column, value);
                },
                
                complete: function() {
                    console.log('Grid loaded successfully');
                }
            };
            
            // Initialize grid
            grid = pq.grid("#products-grid", gridOptions);
            
            // Listen for Livewire updates
            Livewire.on('cell-updated', (data) => {
                notyf.success(data[0].message);
            });
            
            Livewire.on('bulk-update-complete', () => {
                notyf.success('Bulk update completed');
                grid.refreshDataAndView();
            });
            
            // Watch for data changes
            @this.on('gridDataUpdated', (data) => {
                grid.option("dataModel.data", data);
                grid.refreshDataAndView();
            });
        });
    </script>
    @endpush
</div>
```

### **Grid Helper Utility**

```javascript
// resources/js/grid-helpers.js

export class GridHelper {
    static defaultOptions = {
        width: '100%',
        height: 600,
        editable: true,
        editMode: 'cell',
        scrollModel: { autoFit: true },
        selectionModel: { type: 'row', mode: 'range' },
        numberCell: { show: true },
        pageModel: { type: "local", rPP: 100 },
        filterModel: { header: true },
        historyModel: { on: true },
        excel: { on: true, all: true },
        copyModel: { on: true, render: true },
        fillHandle: 'all',
    };
    
    static createGrid(selector, options) {
        const mergedOptions = { ...this.defaultOptions, ...options };
        return pq.grid(selector, mergedOptions);
    }
    
    static formatCurrency(value) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(value);
    }
    
    static exportToExcel(grid, filename) {
        grid.exportData({
            format: 'xlsx',
            filename: filename || 'export.xlsx',
            render: true
        });
    }
}
```

---

## 📊 Database Schema Optimization

### **PostgreSQL-specific Features**

```sql
-- Generated columns
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) GENERATED ALWAYS AS (lower(regexp_replace(name, '[^a-zA-Z0-9]+', '-', 'g'))) STORED,
    retail_price DECIMAL(10,2),
    wholesale_price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    final_price DECIMAL(10,2) GENERATED ALWAYS AS (
        COALESCE(sale_price, retail_price)
    ) STORED,
    search_vector tsvector GENERATED ALWAYS AS (
        to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, ''))
    ) STORED,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Full-text search index
CREATE INDEX idx_products_search ON products USING GIN(search_vector);

-- Partial index for active products
CREATE INDEX idx_products_active ON products(status) WHERE status = 'active';

-- JSONB for flexible attributes
CREATE TABLE product_attributes (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id),
    attributes JSONB NOT NULL DEFAULT '{}'::jsonb
);

-- JSONB index
CREATE INDEX idx_product_attributes_gin ON product_attributes USING GIN(attributes);

-- Example query: Find products with specific attribute
SELECT * FROM product_attributes 
WHERE attributes @> '{"color": "red", "size": "large"}';
```

---

## 🔐 Security Best Practices

### 1. **Role-Based Access Control (RBAC)**
```php
// app/Core/Enums/Role.php
enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SALES_MANAGER = 'sales_manager';
    case SALES_REP = 'sales_rep';
    case WAREHOUSE_MANAGER = 'warehouse_manager';
    case WAREHOUSE_STAFF = 'warehouse_staff';
    case ACCOUNTANT = 'accountant';
    case CUSTOMER_SERVICE = 'customer_service';
}

// app/Core/Enums/Permission.php
enum Permission: string
{
    // Orders
    case VIEW_ORDERS = 'view_orders';
    case CREATE_ORDERS = 'create_orders';
    case EDIT_ORDERS = 'edit_orders';
    case DELETE_ORDERS = 'delete_orders';
    case APPROVE_ORDERS = 'approve_orders';
    
    // Products
    case VIEW_PRODUCTS = 'view_products';
    case MANAGE_PRODUCTS = 'manage_products';
    case SYNC_PRODUCTS = 'sync_products';
    
    // Customers
    case VIEW_CUSTOMERS = 'view_customers';
    case MANAGE_CUSTOMERS = 'manage_customers';
    case VIEW_PRICING = 'view_customer_pricing';
    case MANAGE_PRICING = 'manage_customer_pricing';
    
    // Inventory
    case VIEW_INVENTORY = 'view_inventory';
    case MANAGE_INVENTORY = 'manage_inventory';
    case TRANSFER_INVENTORY = 'transfer_inventory';
    
    // Invoices
    case VIEW_INVOICES = 'view_invoices';
    case CREATE_INVOICES = 'create_invoices';
    case APPROVE_INVOICES = 'approve_invoices';
    
    // Warranty
    case VIEW_WARRANTY_CLAIMS = 'view_warranty_claims';
    case MANAGE_WARRANTY_CLAIMS = 'manage_warranty_claims';
    case APPROVE_WARRANTY_CLAIMS = 'approve_warranty_claims';
}
```

### 2. **Policy-Based Authorization**
```php
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ORDERS);
    }
    
    public function view(User $user, Order $order): bool
    {
        // Sales reps can only see their own orders
        if ($user->hasRole(Role::SALES_REP)) {
            return $order->representative_id === $user->id;
        }
        
        return $user->hasPermission(Permission::VIEW_ORDERS);
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_ORDERS);
    }
    
    public function update(User $user, Order $order): bool
    {
        // Can't edit completed orders
        if ($order->status === OrderStatus::COMPLETED) {
            return false;
        }
        
        return $user->hasPermission(Permission::EDIT_ORDERS);
    }
    
    public function approve(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::APPROVE_ORDERS);
    }
}
```

### 3. **API Security**
```php
// Rate limiting
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function() {
    Route::post('/sync/product', [ProductSyncController::class, 'sync']);
});

// IP Whitelist for sync endpoints
Route::middleware(['auth:sanctum', 'ip.whitelist'])->group(function() {
    Route::post('/sync/order', [OrderSyncController::class, 'sync']);
});
```

---

## 🎯 UPDATED: Complete Permissions & Enums

> **Last Updated:** October 20, 2025  
> **Added Missing Permissions for:** Variants, AddOns, Warehouse, and Invoice Expenses

### **Role Enum (Updated)**
```php
// app/Core/Enums/Role.php
enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case SALES_MANAGER = 'sales_manager';
    case SALES_REP = 'sales_rep';
    case WAREHOUSE_MANAGER = 'warehouse_manager';
    case WAREHOUSE_STAFF = 'warehouse_staff';
    case ACCOUNTANT = 'accountant';
    case CUSTOMER_SERVICE = 'customer_service';
}
```

### **Permission Enum (Complete with Consignment & Quotes)**
```php
// app/Core/Enums/Permission.php
enum Permission: string
{
    // ==================== QUOTES ====================
    case VIEW_QUOTES = 'view_quotes';
    case CREATE_QUOTES = 'create_quotes';
    case EDIT_QUOTES = 'edit_quotes';
    case DELETE_QUOTES = 'delete_quotes';
    case SEND_QUOTES = 'send_quotes';
    case APPROVE_QUOTES = 'approve_quotes';
    case CONVERT_QUOTES_TO_ORDERS = 'convert_quotes_to_orders';
    
    // ==================== ORDERS ====================
    case VIEW_ORDERS = 'view_orders';
    case CREATE_ORDERS = 'create_orders';
    case EDIT_ORDERS = 'edit_orders';
    case DELETE_ORDERS = 'delete_orders';
    case APPROVE_ORDERS = 'approve_orders';
    case CANCEL_ORDERS = 'cancel_orders';
    case VIEW_ALL_ORDERS = 'view_all_orders'; // See all reps' orders
    
    // ==================== PRODUCTS ====================
    case VIEW_PRODUCTS = 'view_products';
    case MANAGE_PRODUCTS = 'manage_products'; // Not needed in sync-only architecture
    case SYNC_PRODUCTS = 'sync_products';
    case VIEW_PRODUCT_COSTS = 'view_product_costs';
    
    // ==================== VARIANTS ====================
    case VIEW_VARIANTS = 'view_variants';
    case MANAGE_VARIANTS = 'manage_variants';
    case SYNC_VARIANTS = 'sync_variants';
    case VIEW_VARIANT_INVENTORY = 'view_variant_inventory';
    case IMPORT_VARIANTS = 'import_variants';
    
    // ==================== ADDONS ====================
    case VIEW_ADDONS = 'view_addons';
    case MANAGE_ADDONS = 'manage_addons';
    case SYNC_ADDONS = 'sync_addons';
    case VIEW_ADDON_PRICING = 'view_addon_pricing';
    case MANAGE_ADDON_CATEGORIES = 'manage_addon_categories';
    
    // ==================== CUSTOMERS ====================
    case VIEW_CUSTOMERS = 'view_customers';
    case CREATE_CUSTOMERS = 'create_customers';
    case EDIT_CUSTOMERS = 'edit_customers';
    case DELETE_CUSTOMERS = 'delete_customers';
    case VIEW_CUSTOMER_PRICING = 'view_customer_pricing';
    case MANAGE_CUSTOMER_PRICING = 'manage_customer_pricing';
    case VIEW_ALL_CUSTOMERS = 'view_all_customers'; // See all reps' customers
    
    // ==================== INVENTORY ====================
    case VIEW_INVENTORY = 'view_inventory';
    case MANAGE_INVENTORY = 'manage_inventory'; // Not needed in sync-only
    case TRANSFER_INVENTORY = 'transfer_inventory';
    case ADJUST_INVENTORY = 'adjust_inventory';
    case VIEW_INVENTORY_COSTS = 'view_inventory_costs';
    
    // ==================== WAREHOUSE ====================
    case VIEW_WAREHOUSES = 'view_warehouses';
    case MANAGE_WAREHOUSES = 'manage_warehouses';
    case CREATE_WAREHOUSES = 'create_warehouses';
    case EDIT_WAREHOUSES = 'edit_warehouses';
    case DELETE_WAREHOUSES = 'delete_warehouses';
    case VIEW_WAREHOUSE_INVENTORY = 'view_warehouse_inventory';
    case TRANSFER_BETWEEN_WAREHOUSES = 'transfer_between_warehouses';
    
    // ==================== CONSIGNMENT (ADDED) ====================
    case VIEW_CONSIGNMENTS = 'view_consignments';
    case CREATE_CONSIGNMENTS = 'create_consignments';
    case EDIT_CONSIGNMENTS = 'edit_consignments';
    case DELETE_CONSIGNMENTS = 'delete_consignments';
    case SEND_CONSIGNMENTS = 'send_consignments';
    case RECORD_CONSIGNMENT_SALES = 'record_consignment_sales';
    case RECORD_CONSIGNMENT_RETURNS = 'record_consignment_returns';
    case GENERATE_CONSIGNMENT_INVOICES = 'generate_consignment_invoices';
    
    // ==================== INVOICES ====================
    case VIEW_INVOICES = 'view_invoices';
    case CREATE_INVOICES = 'create_invoices';
    case EDIT_INVOICES = 'edit_invoices';
    case DELETE_INVOICES = 'delete_invoices';
    case APPROVE_INVOICES = 'approve_invoices';
    case SYNC_INVOICES_TO_WAFEQ = 'sync_invoices_to_wafeq';
    case RECORD_PAYMENTS = 'record_payments';
    case RECORD_EXPENSES = 'record_expenses';
    case VIEW_INVOICE_PROFITS = 'view_invoice_profits';
    case CALCULATE_PROFIT_MARGINS = 'calculate_profit_margins';
    case EXPORT_INVOICES = 'export_invoices';
    
    // ==================== WARRANTY CLAIMS ====================
    case VIEW_WARRANTY_CLAIMS = 'view_warranty_claims';
    case CREATE_WARRANTY_CLAIMS = 'create_warranty_claims';
    case EDIT_WARRANTY_CLAIMS = 'edit_warranty_claims';
    case DELETE_WARRANTY_CLAIMS = 'delete_warranty_claims';
    case APPROVE_WARRANTY_CLAIMS = 'approve_warranty_claims';
    case RESOLVE_WARRANTY_CLAIMS = 'resolve_warranty_claims';
    case VIEW_WARRANTY_COSTS = 'view_warranty_costs';
    
    // ==================== REPORTS ====================
    case VIEW_SALES_REPORTS = 'view_sales_reports';
    case VIEW_INVENTORY_REPORTS = 'view_inventory_reports';
    case VIEW_FINANCIAL_REPORTS = 'view_financial_reports';
    case VIEW_CUSTOMER_REPORTS = 'view_customer_reports';
    case VIEW_WARRANTY_REPORTS = 'view_warranty_reports';
    case EXPORT_REPORTS = 'export_reports';
    
    // ==================== SYNC OPERATIONS ====================
    case MANAGE_SYNC = 'manage_sync';
    case VIEW_SYNC_LOGS = 'view_sync_logs';
    case RETRY_FAILED_SYNCS = 'retry_failed_syncs';
    
    // ==================== SETTINGS ====================
    case VIEW_SETTINGS = 'view_settings';
    case MANAGE_SETTINGS = 'manage_settings';
    case MANAGE_TAX_SETTINGS = 'manage_tax_settings';
    case MANAGE_CURRENCY_SETTINGS = 'manage_currency_settings';
    case MANAGE_COMPANY_BRANDING = 'manage_company_branding';
    case MANAGE_INVOICE_TEMPLATES = 'manage_invoice_templates';
    case MANAGE_QUOTE_TEMPLATES = 'manage_quote_templates';
    case MANAGE_CONSIGNMENT_TEMPLATES = 'manage_consignment_templates';
    case MANAGE_EMAIL_SETTINGS = 'manage_email_settings';
    case MANAGE_PAYMENT_GATEWAYS = 'manage_payment_gateways';
    case MANAGE_INTEGRATION_SETTINGS = 'manage_integration_settings'; // Wafeq, TunerStop, etc.
    
    // ==================== SYSTEM ====================
    case MANAGE_USERS = 'manage_users';
    case MANAGE_ROLES = 'manage_roles';
    case VIEW_AUDIT_LOGS = 'view_audit_logs';
    case MANAGE_SYSTEM_MAINTENANCE = 'manage_system_maintenance';
}
```

### **Quote Status Enum**
```php
// app/Modules/Quotes/Enums/QuoteStatus.php
enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CONVERTED = 'converted'; // Converted to order
    case EXPIRED = 'expired';
    
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent to Customer',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CONVERTED => 'Converted to Order',
            self::EXPIRED => 'Expired',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SENT => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CONVERTED => 'primary',
            self::EXPIRED => 'warning',
        };
    }
}
```

### **Consignment Status Enum**
```php
// app/Modules/Consignment/Enums/ConsignmentStatus.php
enum ConsignmentStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case PARTIALLY_SOLD = 'partially_sold';
    case INVOICED = 'invoiced';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent to Customer',
            self::DELIVERED => 'Delivered',
            self::PARTIALLY_SOLD => 'Partially Sold',
            self::INVOICED => 'Fully Invoiced',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
        };
    }
}
```

---

## 🏢 UPDATED: Module Structure with Quotes

```
app/Modules/
│
├── Quotes/                         # NEW: Separate from Orders
│   ├── Actions/
│   │   ├── CreateQuoteAction.php
│   │   ├── SendQuoteAction.php
│   │   ├── ApproveQuoteAction.php
│   │   ├── ConvertQuoteToOrderAction.php
│   │   └── ExpireQuoteAction.php
│   ├── DTOs/
│   │   ├── QuoteData.php
│   │   └── QuoteItemData.php
│   ├── Enums/
│   │   └── QuoteStatus.php
│   ├── Events/
│   │   ├── QuoteCreated.php
│   │   ├── QuoteSent.php
│   │   ├── QuoteApproved.php
│   │   └── QuoteConverted.php
│   ├── Listeners/
│   │   ├── SendQuoteEmail.php
│   │   └── NotifyQuoteApproval.php
│   ├── Models/
│   │   ├── Quote.php
│   │   └── QuoteItem.php
│   ├── Services/
│   │   ├── QuoteService.php
│   │   ├── QuotePricingService.php
│   │   └── QuoteConversionService.php
│   ├── Filament/
│   │   └── Resources/
│   │       └── QuoteResource.php
│   └── Policies/
│       └── QuotePolicy.php
│
├── Orders/                         # Now sync-only (read from external)
│   ├── Models/
│   │   ├── Order.php (read-only from sync)
│   │   └── OrderItem.php
│   ├── Services/
│   │   ├── OrderSyncService.php
│   │   └── OrderFulfillmentService.php
│   ├── Filament/
│   │   └── Resources/
│   │       └── OrderResource.php (view/track only)
│   └── Jobs/
│       └── SyncOrderJob.php
│
├── Consignment/                    # UPDATED: Full permissions
│   ├── Actions/
│   │   ├── CreateConsignmentAction.php
│   │   ├── SendConsignmentAction.php
│   │   ├── RecordSaleAction.php
│   │   ├── RecordReturnAction.php
│   │   └── GenerateConsignmentInvoiceAction.php
│   ├── DTOs/
│   │   ├── ConsignmentData.php
│   │   └── ConsignmentItemData.php
│   ├── Enums/
│   │   └── ConsignmentStatus.php
│   ├── Models/
│   │   ├── Consignment.php
│   │   └── ConsignmentItem.php
│   ├── Services/
│   │   └── ConsignmentService.php
│   ├── Filament/
│   │   └── Resources/
│   │       └── ConsignmentResource.php
│   └── Policies/
│       └── ConsignmentPolicy.php
│
├── Products/                       # Sync-only (read from TunerStop)
│   ├── Models/
│   │   ├── Product.php (synced, read-only)
│   │   └── ProductImage.php
│   ├── Services/
│   │   └── ProductSyncService.php
│   └── Jobs/
│       └── SyncProductJob.php
│
└── ...other modules
```

---

## ⚙️ SETTINGS MODULE - System Configuration

### Overview
The Settings module manages all system-wide configurations including tax rates, currency, company branding, and document templates used in invoices, quotes, and consignments.

### Database Schema

```sql
-- Settings table (key-value pairs)
CREATE TABLE settings (
    id BIGSERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL,  -- tax, currency, company, email, integration
    key VARCHAR(100) NOT NULL,
    value TEXT,
    type VARCHAR(20) DEFAULT 'string',  -- string, number, boolean, json, file
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,  -- Can be accessed in frontend
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(category, key)
);

-- Company branding
CREATE TABLE company_branding (
    id BIGSERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    company_logo VARCHAR(500),  -- S3 path
    company_address TEXT,
    company_phone VARCHAR(50),
    company_email VARCHAR(100),
    company_website VARCHAR(255),
    
    -- Tax information
    tax_registration_number VARCHAR(100),  -- TRN/VAT number
    commercial_registration VARCHAR(100),
    
    -- Document footer
    footer_text TEXT,
    
    -- Colors (for templates)
    primary_color VARCHAR(7) DEFAULT '#1F2937',
    secondary_color VARCHAR(7) DEFAULT '#3B82F6',
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Document templates
CREATE TABLE document_templates (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,  -- invoice, quote, consignment, warranty
    template_path VARCHAR(500),  -- Blade template path
    is_default BOOLEAN DEFAULT FALSE,
    
    -- Template configuration (JSONB)
    config JSONB DEFAULT '{}'::jsonb,  -- Header/footer settings, field visibility, etc.
    
    -- Template preview
    preview_image VARCHAR(500),
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_settings_category ON settings(category);
CREATE INDEX idx_document_templates_type ON document_templates(type);
```

### Settings Model

```php
// app/Modules/Settings/Models/Setting.php
namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'category',
        'key',
        'value',
        'type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    // Get setting value with type casting
    public function getTypedValue()
    {
        return match($this->type) {
            'number' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    // Static helper methods
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->getTypedValue() : $default;
    }

    public static function set(string $category, string $key, $value, string $type = 'string'): self
    {
        return self::updateOrCreate(
            ['category' => $category, 'key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type,
            ]
        );
    }

    // Scopes
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
```

### Settings Service

```php
// app/Modules/Settings/Services/SettingsService.php
namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected const CACHE_TTL = 3600; // 1 hour

    public function getTaxRate(): float
    {
        return Cache::remember('setting.tax_rate', self::CACHE_TTL, function () {
            return (float) Setting::get('tax_rate', 5.0); // Default 5% VAT
        });
    }

    public function getCurrency(): string
    {
        return Cache::remember('setting.currency', self::CACHE_TTL, function () {
            return Setting::get('currency', 'AED');
        });
    }

    public function getCurrencySymbol(): string
    {
        $currency = $this->getCurrency();
        
        return match($currency) {
            'AED' => 'AED',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency,
        };
    }

    public function getCompanyLogo(): ?string
    {
        return Cache::remember('setting.company_logo', self::CACHE_TTL, function () {
            return Setting::get('company_logo');
        });
    }

    public function getAllTaxSettings(): array
    {
        return Cache::remember('settings.tax', self::CACHE_TTL, function () {
            return [
                'tax_rate' => $this->getTaxRate(),
                'tax_label' => Setting::get('tax_label', 'VAT'),
                'tax_registration_number' => Setting::get('tax_registration_number'),
                'tax_inclusive_by_default' => Setting::get('tax_inclusive_by_default', true),
            ];
        });
    }

    public function getAllCurrencySettings(): array
    {
        return Cache::remember('settings.currency', self::CACHE_TTL, function () {
            return [
                'currency' => $this->getCurrency(),
                'currency_symbol' => $this->getCurrencySymbol(),
                'currency_position' => Setting::get('currency_position', 'before'), // before/after amount
                'decimal_separator' => Setting::get('decimal_separator', '.'),
                'thousand_separator' => Setting::get('thousand_separator', ','),
                'decimal_places' => (int) Setting::get('decimal_places', 2),
            ];
        });
    }

    public function getInvoiceTemplate(): string
    {
        return Setting::get('invoice_template', 'professional-invoice');
    }

    public function getQuoteTemplate(): string
    {
        return Setting::get('quote_template', 'professional-quote');
    }

    public function getConsignmentTemplate(): string
    {
        return Setting::get('consignment_template', 'professional-consignment');
    }

    public function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $category = $this->determineCategoryFromKey($key);
            $type = $this->determineTypeFromValue($value);
            
            Setting::set($category, $key, $value, $type);
        }

        // Clear settings cache
        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget('setting.tax_rate');
        Cache::forget('setting.currency');
        Cache::forget('setting.company_logo');
        Cache::forget('settings.tax');
        Cache::forget('settings.currency');
    }

    protected function determineCategoryFromKey(string $key): string
    {
        if (str_starts_with($key, 'tax_')) return 'tax';
        if (str_starts_with($key, 'currency_')) return 'currency';
        if (str_starts_with($key, 'company_')) return 'company';
        if (str_starts_with($key, 'email_')) return 'email';
        if (str_starts_with($key, 'wafeq_') || str_starts_with($key, 'tunerstop_')) return 'integration';
        
        return 'general';
    }

    protected function determineTypeFromValue($value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_numeric($value)) return 'number';
        if (is_array($value)) return 'json';
        
        return 'string';
    }
}
```

### Company Branding Model

```php
// app/Modules/Settings/Models/CompanyBranding.php
namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyBranding extends Model
{
    protected $table = 'company_branding';

    protected $fillable = [
        'company_name',
        'company_logo',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'tax_registration_number',
        'commercial_registration',
        'footer_text',
        'primary_color',
        'secondary_color',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->company_logo) {
            return null;
        }

        return Storage::disk('s3')->url($this->company_logo);
    }

    public static function current(): ?self
    {
        return self::first() ?? self::create([
            'company_name' => config('app.name'),
            'primary_color' => '#1F2937',
            'secondary_color' => '#3B82F6',
        ]);
    }
}
```

### Document Template Model

```php
// app/Modules/Settings/Models/DocumentTemplate.php
namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'template_path',
        'is_default',
        'config',
        'preview_image',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'config' => 'array',
    ];

    public static function getDefaultForType(string $type): ?self
    {
        return self::where('type', $type)
            ->where('is_default', true)
            ->first();
    }

    public function setAsDefault(): void
    {
        // Unset other defaults for this type
        self::where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
```

### Filament Settings Resource

```php
// app/Modules/Settings/Filament/Resources/SettingsResource.php
namespace App\Modules\Settings\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use App\Modules\Settings\Services\SettingsService;

class SettingsResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'System';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Settings')
                ->tabs([
                    // Tax Settings Tab
                    Forms\Components\Tabs\Tab::make('Tax Settings')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Forms\Components\TextInput::make('tax_rate')
                                ->label('Tax Rate (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(5)
                                ->suffix('%')
                                ->required(),
                            
                            Forms\Components\TextInput::make('tax_label')
                                ->label('Tax Label')
                                ->default('VAT')
                                ->required(),
                            
                            Forms\Components\TextInput::make('tax_registration_number')
                                ->label('Tax Registration Number (TRN)')
                                ->placeholder('e.g., 123456789012345'),
                            
                            Forms\Components\Toggle::make('tax_inclusive_by_default')
                                ->label('Tax Inclusive by Default')
                                ->default(true),
                        ]),

                    // Currency Settings Tab
                    Forms\Components\Tabs\Tab::make('Currency Settings')
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Forms\Components\Select::make('currency')
                                ->label('Currency')
                                ->options([
                                    'AED' => 'AED - UAE Dirham',
                                    'USD' => 'USD - US Dollar',
                                    'EUR' => 'EUR - Euro',
                                    'GBP' => 'GBP - British Pound',
                                    'SAR' => 'SAR - Saudi Riyal',
                                ])
                                ->default('AED')
                                ->required(),
                            
                            Forms\Components\Select::make('currency_position')
                                ->label('Currency Symbol Position')
                                ->options([
                                    'before' => 'Before amount (e.g., AED 100)',
                                    'after' => 'After amount (e.g., 100 AED)',
                                ])
                                ->default('before'),
                            
                            Forms\Components\TextInput::make('decimal_separator')
                                ->label('Decimal Separator')
                                ->default('.')
                                ->maxLength(1),
                            
                            Forms\Components\TextInput::make('thousand_separator')
                                ->label('Thousand Separator')
                                ->default(',')
                                ->maxLength(1),
                            
                            Forms\Components\TextInput::make('decimal_places')
                                ->label('Decimal Places')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(4)
                                ->default(2),
                        ]),

                    // Company Branding Tab
                    Forms\Components\Tabs\Tab::make('Company Branding')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Forms\Components\TextInput::make('company_name')
                                ->label('Company Name')
                                ->required(),
                            
                            Forms\Components\FileUpload::make('company_logo')
                                ->label('Company Logo')
                                ->image()
                                ->disk('s3')
                                ->directory('branding')
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml'])
                                ->maxSize(2048) // 2MB
                                ->helperText('Used in invoices, quotes, and consignments'),
                            
                            Forms\Components\Textarea::make('company_address')
                                ->label('Company Address')
                                ->rows(3),
                            
                            Forms\Components\TextInput::make('company_phone')
                                ->label('Phone Number')
                                ->tel(),
                            
                            Forms\Components\TextInput::make('company_email')
                                ->label('Email')
                                ->email(),
                            
                            Forms\Components\TextInput::make('company_website')
                                ->label('Website')
                                ->url(),
                            
                            Forms\Components\TextInput::make('commercial_registration')
                                ->label('Commercial Registration Number'),
                            
                            Forms\Components\ColorPicker::make('primary_color')
                                ->label('Primary Brand Color')
                                ->default('#1F2937'),
                            
                            Forms\Components\ColorPicker::make('secondary_color')
                                ->label('Secondary Brand Color')
                                ->default('#3B82F6'),
                        ]),

                    // Document Templates Tab
                    Forms\Components\Tabs\Tab::make('Document Templates')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Select::make('invoice_template')
                                ->label('Invoice Template')
                                ->options([
                                    'professional-invoice' => 'Professional Invoice',
                                    'modern-invoice' => 'Modern Invoice',
                                    'classic-invoice' => 'Classic Invoice',
                                ])
                                ->default('professional-invoice'),
                            
                            Forms\Components\Select::make('quote_template')
                                ->label('Quote Template')
                                ->options([
                                    'professional-quote' => 'Professional Quote',
                                    'modern-quote' => 'Modern Quote',
                                ])
                                ->default('professional-quote'),
                            
                            Forms\Components\Select::make('consignment_template')
                                ->label('Consignment Template')
                                ->options([
                                    'professional-consignment' => 'Professional Consignment',
                                ])
                                ->default('professional-consignment'),
                            
                            Forms\Components\Textarea::make('invoice_footer_text')
                                ->label('Invoice Footer Text')
                                ->rows(2)
                                ->placeholder('Thank you for your business!'),
                            
                            Forms\Components\Textarea::make('quote_footer_text')
                                ->label('Quote Footer Text')
                                ->rows(2)
                                ->placeholder('Valid for 30 days from quote date'),
                        ]),

                    // Integration Settings Tab
                    Forms\Components\Tabs\Tab::make('Integrations')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->schema([
                            Forms\Components\Section::make('Wafeq Accounting')
                                ->schema([
                                    Forms\Components\TextInput::make('wafeq_api_url')
                                        ->label('API URL')
                                        ->url(),
                                    
                                    Forms\Components\TextInput::make('wafeq_api_key')
                                        ->label('API Key')
                                        ->password(),
                                    
                                    Forms\Components\Toggle::make('wafeq_auto_sync')
                                        ->label('Auto-sync to Wafeq')
                                        ->default(true),
                                ]),
                            
                            Forms\Components\Section::make('TunerStop Integration')
                                ->schema([
                                    Forms\Components\TextInput::make('tunerstop_api_url')
                                        ->label('API URL')
                                        ->url(),
                                    
                                    Forms\Components\TextInput::make('tunerstop_api_token')
                                        ->label('API Token')
                                        ->password(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function afterSave($record): void
    {
        // Clear settings cache after save
        app(SettingsService::class)->clearCache();
    }
}
```

### Usage in Templates

```blade
{{-- resources/views/pdf/invoice.blade.php --}}
@php
    $settingsService = app(\App\Modules\Settings\Services\SettingsService::class);
    $branding = \App\Modules\Settings\Models\CompanyBranding::current();
    $taxRate = $settingsService->getTaxRate();
    $currency = $settingsService->getCurrencySymbol();
@endphp

<!DOCTYPE html>
<html>
<head>
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        :root {
            --primary-color: {{ $branding->primary_color }};
            --secondary-color: {{ $branding->secondary_color }};
        }
        /* ... rest of styles */
    </style>
</head>
<body>
    <div class="header">
        @if($branding->company_logo)
            <img src="{{ $branding->logo_url }}" alt="{{ $branding->company_name }}" class="logo">
        @endif
        
        <div class="company-info">
            <h1>{{ $branding->company_name }}</h1>
            <p>{{ $branding->company_address }}</p>
            <p>Phone: {{ $branding->company_phone }}</p>
            <p>Email: {{ $branding->company_email }}</p>
            @if($branding->tax_registration_number)
                <p>TRN: {{ $branding->tax_registration_number }}</p>
            @endif
        </div>
    </div>

    {{-- Invoice content --}}
    <div class="invoice-details">
        <h2>INVOICE</h2>
        <p>Invoice #: {{ $invoice->invoice_number }}</p>
        <p>Date: {{ $invoice->invoice_date->format('Y-m-d') }}</p>
    </div>

    {{-- Items table --}}
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $currency }} {{ number_format($item->price, 2) }}</td>
                    <td>{{ $currency }} {{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Subtotal:</td>
                <td>{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3">VAT ({{ $taxRate }}%):</td>
                <td>{{ $currency }} {{ number_format($invoice->tax_amount, 2) }}</td>
            </tr>
            <tr class="total">
                <td colspan="3"><strong>Total:</strong></td>
                <td><strong>{{ $currency }} {{ number_format($invoice->total, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>{{ $settingsService::get('invoice_footer_text', 'Thank you for your business!') }}</p>
    </div>
</body>
</html>
```

### Seeder for Default Settings

```php
// database/seeders/DefaultSettingsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\CompanyBranding;

class DefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Tax Settings
        Setting::set('tax', 'tax_rate', 5.0, 'number');
        Setting::set('tax', 'tax_label', 'VAT', 'string');
        Setting::set('tax', 'tax_inclusive_by_default', true, 'boolean');

        // Currency Settings
        Setting::set('currency', 'currency', 'AED', 'string');
        Setting::set('currency', 'currency_position', 'before', 'string');
        Setting::set('currency', 'decimal_separator', '.', 'string');
        Setting::set('currency', 'thousand_separator', ',', 'string');
        Setting::set('currency', 'decimal_places', 2, 'number');

        // Template Settings
        Setting::set('templates', 'invoice_template', 'professional-invoice', 'string');
        Setting::set('templates', 'quote_template', 'professional-quote', 'string');
        Setting::set('templates', 'consignment_template', 'professional-consignment', 'string');

        // Company Branding
        CompanyBranding::create([
            'company_name' => config('app.name'),
            'primary_color' => '#1F2937',
            'secondary_color' => '#3B82F6',
        ]);
    }
}
```

---

This is Part 1 of the System Architecture document. 

**Shall I continue with:**
1. ✅ **PART 2:** Detailed Implementation Plan with Phases & TODOs
2. ✅ **PART 3:** Module-by-Module Implementation Guide
3. ✅ **PART 4:** Testing Strategy & Quality Assurance
4. ✅ **PART 5:** Deployment & DevOps Strategy

**Let me know and I'll create the complete implementation plan!** 🚀
