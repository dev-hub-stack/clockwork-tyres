# Warranty Claims Module - Quick Start Checklist

**Module:** Warranty Claims  
**Pattern:** Same as Quotes, Invoices, Consignments  
**Estimated Time:** 35 hours (1 week)  
**Priority:** Medium (after AddOns, Orders, Inventory Grid)

---

## ✅ PREPARATION CHECKLIST

### Before Starting
- [ ] Review mock-ups (provided in attachments)
- [ ] Review ConsignmentResource.php (copy as template)
- [ ] Review InvoiceResource.php (for action tooltips pattern)
- [ ] Answer business logic questions (see below)
- [ ] Create module directory structure

---

## 📋 PHASE-BY-PHASE CHECKLIST

### Phase 1: Database & Models (4-5 hours) ⭐ START HERE

#### Enums (30 minutes)
- [ ] Create `app/Modules/Warranties/Enums/WarrantyClaimStatus.php`
- [ ] Create `app/Modules/Warranties/Enums/ClaimActionType.php`
- [ ] Create `app/Modules/Warranties/Enums/ResolutionAction.php`

#### Migrations (1.5 hours)
- [ ] Create `2025_11_XX_create_warranty_claims_table.php`
  - Fields: claim_number, customer_id, warehouse_id, representative_id, status, dates, notes
- [ ] Create `2025_11_XX_create_warranty_claim_items_table.php`
  - Fields: warranty_claim_id, product_id, quantity, issue_description, resolution_action
- [ ] Create `2025_11_XX_create_warranty_claim_history_table.php`
  - Fields: warranty_claim_id, user_id, action_type, description, metadata
- [ ] Run migrations: `php artisan migrate`

#### Models (2 hours)
- [ ] Create `app/Modules/Warranties/Models/WarrantyClaim.php`
  - Add fillable fields
  - Add casts (status enum, dates, decimals)
  - Add relationships: customer(), warehouse(), representative(), items(), histories()
  - Add methods: addNote(), addVideoLink(), markAsReplaced(), markAsClaimed(), void()
  - Add scopes: recent(), byStatus(), pending(), resolved()

- [ ] Create `app/Modules/Warranties/Models/WarrantyClaimItem.php`
  - Add fillable fields
  - Add relationships: warrantyClaim(), product(), productVariant()

- [ ] Create `app/Modules/Warranties/Models/WarrantyClaimHistory.php`
  - Add fillable fields
  - Add relationships: warrantyClaim(), user()
  - Add casts: action_type (enum), metadata (array)

#### Verify Phase 1
- [ ] Run `php artisan tinker`
- [ ] Test: `\App\Modules\Warranties\Models\WarrantyClaim::count()`
- [ ] Test: Create sample claim manually

---

### Phase 2: Filament Resource (6-8 hours)

#### Base Resource (30 minutes)
- [ ] Create `app/Filament/Resources/WarrantyClaimResource.php`
  - Copy from ConsignmentResource.php as template
  - Set model: WarrantyClaim::class
  - Set navigation icon: 'heroicon-o-shield-check'
  - Set navigation group: 'Sales'
  - Set navigation label: 'Warranty Claims'
  - Add eager loading: customer, warehouse, items, histories

#### Form Schema (2 hours)
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Schemas/WarrantyClaimForm.php`
  - Section: Claim Information (customer, warehouse, rep, dates)
  - Section: Claimed Items (repeater with product, quantity, issue)
  - Section: Notes (notes, internal_notes)

#### Table Schema (2 hours)
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Tables/WarrantyClaimsTable.php`
  - Columns: claim_date, claim_number, customer, status badge, items_count
  - Filters: status, warehouse, date range
  - Actions: View, Edit, Delete (with tooltips)
  - Bulk Actions: Export, Mark as Replaced, Mark as Claimed

#### Infolist Schema (2-3 hours) ⭐ KEY FEATURE
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Schemas/WarrantyClaimInfolist.php`
  - Section: Claim Details
  - Section: Claimed Items (table)
  - Section: **Claim History (scrollable timeline)** - IMPORTANT!
    - Show all history entries
    - Video links clickable
    - Notes readable
    - Color-coded by action type
    - Reverse chronological order

#### Verify Phase 2
- [ ] Access `/admin/warranty-claims`
- [ ] Create test claim via UI
- [ ] Verify form saves correctly
- [ ] Verify table displays data
- [ ] Verify view page shows history

---

### Phase 3: Custom Actions (4-5 hours)

#### Action Classes (3 hours)
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/AddNoteAction.php`
  - Modal with textarea
  - Save to warranty_claim_history
  - Refresh view

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/AddVideoLinkAction.php`
  - Modal with URL input
  - Validate URL
  - Save to history with metadata

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/MarkAsReplacedAction.php`
  - Change status to 'replaced'
  - Log to history
  - Optional: Create replacement order

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/MarkAsClaimedAction.php`
  - Change status to 'claimed'
  - Set resolution_date
  - Log to history

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/VoidClaimAction.php`
  - Change status to 'void'
  - Require reason
  - Cannot be undone

#### Tooltips (1 hour)
- [ ] Add tooltips to all actions (copy from InvoiceResource pattern):
  - View: "View complete claim details and history"
  - Edit: "Modify claim information"
  - Add Note: "Add internal or customer-facing note"
  - Add Video Link: "Add video evidence link"
  - Mark as Replaced: "Mark items as replaced and close claim"
  - Mark as Claimed: "Mark claim as valid and processed"
  - Void: "Cancel claim (cannot be undone)"
  - Delete: "Permanently delete claim"

#### Verify Phase 3
- [ ] Test Add Note action
- [ ] Test Add Video Link action
- [ ] Test Mark as Replaced
- [ ] Test Mark as Claimed
- [ ] Test Void action
- [ ] Verify history updates correctly

---

### Phase 4: Pages (2-3 hours)

#### Page Classes (2 hours)
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/ListWarrantyClaims.php`
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/CreateWarrantyClaim.php`
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/EditWarrantyClaim.php`
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/ViewWarrantyClaim.php`
  - Add header actions: Add Note, Add Video Link, Mark as Replaced, etc.
  - Add attachments widget

#### Verify Phase 4
- [ ] Test navigation between pages
- [ ] Test create flow
- [ ] Test edit flow
- [ ] Test view page with all actions

---

### Phase 5: Services (3-4 hours)

#### Service Classes (3 hours)
- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimService.php`
  - createClaim()
  - updateClaim()
  - addNote()
  - addVideoLink()
  - markAsReplaced()
  - markAsClaimed()
  - voidClaim()
  - generateClaimNumber()

- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimHistoryService.php`
  - logAction()
  - getHistoryForClaim()
  - formatHistoryEntry()

#### Verify Phase 5
- [ ] Test each service method
- [ ] Verify history logging
- [ ] Verify claim number generation

---

### Phase 6: PDF & Email (4-5 hours)

#### PDF Generation (2 hours)
- [ ] Install: `composer require barryvdh/laravel-dompdf`
- [ ] Create `resources/views/pdfs/warranty_claim.blade.php`
- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimPdfService.php`
- [ ] Add Print/Download action to view page

#### Email Notifications (2 hours)
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimCreated.php`
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimUpdated.php`
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimResolved.php`
- [ ] Create email templates in `resources/views/emails/warranties/`

#### Verify Phase 6
- [ ] Generate test PDF
- [ ] Send test email
- [ ] Verify formatting

---

### Phase 7: Testing (4-5 hours)

#### Test Scripts (4 hours)
- [ ] Create `test_warranty_claim_actions.php`
  - Test create, add note, add video, mark as replaced, mark as claimed, void
- [ ] Create `test_warranty_claim_history.php`
  - Test history logging and retrieval
- [ ] Create `test_warranty_claim_statuses.php`
  - Test status transitions
- [ ] Create `test_all_warranty_actions.php`
  - Master test runner

#### Test Data (1 hour)
- [ ] Create `database/seeders/WarrantyClaimSeeder.php`
  - Seed 10-15 warranty claims
  - Include all statuses
  - Include history entries

#### Verify Phase 7
- [ ] Run all tests: `php test_all_warranty_actions.php`
- [ ] Verify 100% pass rate
- [ ] Run seeder: `php artisan db:seed --class=WarrantyClaimSeeder`
- [ ] Verify data in database

---

### Phase 8: Documentation (2-3 hours)

#### Documentation Files (2 hours)
- [ ] Create `docs/architecture/ARCHITECTURE_WARRANTY_MODULE.md`
- [ ] Create `WARRANTY_CLAIMS_IMPLEMENTATION.md`
- [ ] Create `WARRANTY_CLAIMS_TESTS_README.md`
- [ ] Create `WARRANTY_CLAIMS_USER_GUIDE.md`

#### Git Commit (30 minutes)
- [ ] Stage all files
- [ ] Commit with message: "Complete Warranty Claims module - v1.0"
- [ ] Update PROGRESS_SUMMARY.md (70% → 75%)

---

## ⚠️ BUSINESS LOGIC QUESTIONS (ANSWER BEFORE STARTING)

### Critical Questions
1. **Warranty Period:**
   - [ ] Do products have warranty duration (e.g., 1 year, 2 years)?
   - [ ] Should system auto-check if claim is within warranty period?
   - [ ] What happens to claims outside warranty period?

2. **Inventory Impact:**
   - [ ] When marking as "Replaced", should system:
     - [ ] Auto-create replacement order/invoice?
     - [ ] Auto-allocate replacement items from inventory?
     - [ ] Track original item as returned to warehouse?

3. **Claim Workflow:**
   - [ ] Is there approval workflow? (draft → pending → approved → resolved)
   - [ ] Who can approve claims? (Sales Rep, Manager, Admin?)
   - [ ] Can customers see claim status online?

4. **Multiple Items:**
   - [ ] Can one claim have multiple products?
   - [ ] Can same product appear multiple times (different issues)?

5. **Notifications:**
   - [ ] Who receives emails when claim created? (Customer, Sales Rep, both?)
   - [ ] Who receives emails when claim resolved?
   - [ ] Send email on every status change?

6. **Return Tracking:**
   - [ ] Track physical return of defective items?
   - [ ] Require return before issuing replacement?
   - [ ] Track condition of returned items?

### Technical Questions
7. **Video Links:**
   - [ ] Support only Google Drive?
   - [ ] Also support YouTube, Vimeo, other platforms?
   - [ ] Validate URL or allow any link?

8. **File Attachments:**
   - [ ] Max file size limit?
   - [ ] Allowed file types? (images, PDFs, videos?)
   - [ ] Store locally or S3?

9. **Claim Number Format:**
   - [ ] Use auto-increment (2392130)?
   - [ ] Or custom format (WC-2025-0001)?
   - [ ] Start from specific number?

10. **History Retention:**
    - [ ] Keep all history forever?
    - [ ] Auto-delete after X months?
    - [ ] Allow manual history deletion?

---

## 🎯 SUCCESS CHECKLIST

### Functionality
- [ ] Can create warranty claims
- [ ] Can add notes (visible in history)
- [ ] Can add video links (clickable)
- [ ] Can upload attachments
- [ ] Can mark as replaced/claimed
- [ ] Can void claims
- [ ] History timeline works
- [ ] Status badges colored correctly
- [ ] PDF generation works
- [ ] Email sending works

### UI/UX
- [ ] Matches mock-up design
- [ ] Scrollable history section
- [ ] Tooltips helpful
- [ ] Modals functional
- [ ] Table sortable/filterable

### Testing
- [ ] All tests pass (100%)
- [ ] 10+ claims seeded
- [ ] History logging verified

---

## 📊 PROGRESS TRACKING

### Daily Log
**Day 1:**
- [ ] Phase 1 complete (Database & Models)
- [ ] Database verified with sample data

**Day 2:**
- [ ] Phase 2 complete (Filament Resource)
- [ ] UI accessible and functional

**Day 3:**
- [ ] Phase 3 complete (Custom Actions)
- [ ] All actions working

**Day 4:**
- [ ] Phase 4 complete (Pages)
- [ ] Phase 5 complete (Services)

**Day 5:**
- [ ] Phase 6 complete (PDF & Email)
- [ ] Phase 7 complete (Testing)

**Day 6:**
- [ ] Phase 8 complete (Documentation)
- [ ] Module 100% complete
- [ ] Git committed

---

## 🚀 QUICK START COMMANDS

```bash
# Create module directory
mkdir -p app/Modules/Warranties/{Models,Enums,Services,Mail}

# Create migration files
php artisan make:migration create_warranty_claims_table
php artisan make:migration create_warranty_claim_items_table
php artisan make:migration create_warranty_claim_history_table

# Create model files
php artisan make:model Modules/Warranties/Models/WarrantyClaim
php artisan make:model Modules/Warranties/Models/WarrantyClaimItem
php artisan make:model Modules/Warranties/Models/WarrantyClaimHistory

# Run migrations
php artisan migrate

# Create seeder
php artisan make:seeder WarrantyClaimSeeder

# Test database
php artisan tinker
>>> \App\Modules\Warranties\Models\WarrantyClaim::count()
```

---

**Ready to start? Begin with Phase 1!** 🎯
