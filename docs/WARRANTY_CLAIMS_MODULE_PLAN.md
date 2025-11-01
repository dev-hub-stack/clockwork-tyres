# Warranty Claims Module - Implementation Plan

**Created:** November 1, 2025  
**Based on:** Mock-ups provided + Existing patterns (Quotes, Invoices, Consignments)  
**Status:** Planning Phase

---

## 📸 ANALYSIS OF MOCK-UPS

### Screenshot 1: Warranty Claims List View
**Features Identified:**
- Table with columns: DATE, NUMBER, CUSTOMER, STATUS, QUANTITY
- Status badges: RETURNED (blue), REPLACED (yellow), CLAIMED (green)
- Sort, Filter, Export buttons (top right)
- Add button (top right)
- Search functionality
- Navigation: Sales > Warranty Claims

### Screenshot 2: Warranty Claim Detail View
**Features Identified:**
- Left sidebar: List of warranty claims with status badges
- Main panel: Warranty Claim detail (Claim #342523)
- **CLAIM HISTORY** section (scrollable):
  - Timeline format: Date + Description
  - Example: "2025-04-09: Link added: googledrive.com/..."
  - Example: "2025-04-10: Note added: Damage piece not returned, replacement issued"
- Action buttons:
  - "Add Video Link" (yellow)
  - "Add Note" (yellow)
  - "Void" button
  - "Print / Download" button
  - "Mark as" dropdown (replaced or claimed)
  - "Send" button
  - "Edit" button
- Attachment count: "Attachments: 0"
- Customer details
- Warehouse details
- Tax Registration Numbers

### Screenshot 3: Add Note/Video Link Modal
**Features Identified:**
- Modal overlay: "Add Note/Video Link"
- Large text area: "Type here..."
- Submit button (purple)
- Clean, simple interface

---

## 🎯 MODULE REQUIREMENTS

Based on mock-ups and existing patterns, the Warranty Claims module needs:

### Core Features
1. ✅ Warranty Claim CRUD
2. ✅ Claim status tracking (Draft, Pending, Replaced, Claimed, Returned, Void)
3. ✅ Claim history/timeline (scrollable)
4. ✅ Add notes to claims
5. ✅ Add video links (Google Drive, YouTube, etc.)
6. ✅ File attachments
7. ✅ Customer relationship
8. ✅ Product relationship (items being claimed)
9. ✅ Warehouse tracking
10. ✅ Status badges (color-coded)
11. ✅ Print/Download functionality
12. ✅ Email notifications
13. ✅ Mark as Replaced/Claimed actions

### Database Structure
```
warranty_claims:
- id
- claim_number (auto-generated: like "2392130")
- customer_id (FK)
- warehouse_id (FK)
- representative_id (FK)
- invoice_id (FK) ⭐ NEW - Link to invoice being claimed
- status (enum: draft, pending, replaced, claimed, returned, void)
- issue_date
- claim_date
- resolution_date
- notes
- internal_notes
- created_by
- resolved_by
- timestamps
- soft_deletes

warranty_claim_items:
- id
- warranty_claim_id (FK)
- product_id or product_variant_id (FK)
- order_id (FK - original order)
- order_item_id (FK - original order item)
- invoice_id (FK) ⭐ NEW - Link to specific invoice
- invoice_item_id (FK) ⭐ NEW - Link to specific invoice item
- quantity
- issue_description
- resolution_action (replace, refund, repair)
- timestamps

warranty_claim_history:
- id
- warranty_claim_id (FK)
- user_id (FK)
- action_type (note_added, video_link_added, status_changed, file_attached)
- description (text)
- metadata (json - for video links, file paths, etc.)
- timestamps
```

---

## 📋 IMPLEMENTATION TODO LIST

### Phase 1: Database & Models (4-5 hours)

#### Task 1.1: Create Enums
- [ ] Create `app/Modules/Warranties/Enums/WarrantyClaimStatus.php`
  ```php
  enum WarrantyClaimStatus: string {
      case DRAFT = 'draft';
      case PENDING = 'pending';
      case REPLACED = 'replaced';
      case CLAIMED = 'claimed';
      case RETURNED = 'returned';
      case VOID = 'void';
  }
  ```
- [ ] Create `app/Modules/Warranties/Enums/ClaimActionType.php`
  ```php
  enum ClaimActionType: string {
      case NOTE_ADDED = 'note_added';
      case VIDEO_LINK_ADDED = 'video_link_added';
      case STATUS_CHANGED = 'status_changed';
      case FILE_ATTACHED = 'file_attached';
      case EMAIL_SENT = 'email_sent';
      case RESOLVED = 'resolved';
  }
  ```
- [ ] Create `app/Modules/Warranties/Enums/ResolutionAction.php`
  ```php
  enum ResolutionAction: string {
      case REPLACE = 'replace';
      case REFUND = 'refund';
      case REPAIR = 'repair';
      case NO_ACTION = 'no_action';
  }
  ```

#### Task 1.2: Create Migrations
- [ ] Create migration: `create_warranty_claims_table.php`
  - claim_number (unique, indexed)
  - customer_id, warehouse_id, representative_id (foreign keys)
  - invoice_id (nullable foreign key) ⭐ NEW - Link to invoice
  - status (enum)
  - dates (issue_date, claim_date, resolution_date)
  - notes, internal_notes (text)
  - created_by, resolved_by (foreign keys)
  - timestamps, soft_deletes

- [ ] Create migration: `create_warranty_claim_items_table.php`
  - warranty_claim_id (foreign key)
  - product_id, product_variant_id (nullable foreign keys)
  - order_id, order_item_id (nullable foreign keys - reference original purchase)
  - invoice_id, invoice_item_id (nullable foreign keys) ⭐ NEW - Link to invoice items
  - quantity, issue_description, resolution_action
  - timestamps

- [ ] Create migration: `create_warranty_claim_history_table.php`
  - warranty_claim_id (foreign key)
  - user_id (foreign key)
  - action_type (enum)
  - description (text)
  - metadata (json)
  - timestamps

#### Task 1.3: Create Models
- [ ] Create `app/Modules/Warranties/Models/WarrantyClaim.php`
  - Relationships: customer, warehouse, representative, invoice ⭐ NEW, items, histories, createdBy, resolvedBy
  - Casts: status (enum), dates
  - Scopes: recent, byStatus, pending, resolved
  - Methods: addHistory, addNote, addVideoLink, markAsReplaced, markAsClaimed, void

- [ ] Create `app/Modules/Warranties/Models/WarrantyClaimItem.php`
  - Relationships: warrantyClaim, product, productVariant, order, orderItem, invoice ⭐ NEW, invoiceItem ⭐ NEW
  - Casts: quantity (integer)

- [ ] Create `app/Modules/Warranties/Models/WarrantyClaimHistory.php`
  - Relationships: warrantyClaim, user
  - Casts: action_type (enum), metadata (array)
  - Accessors: formatted description, user name

---

### Phase 2: Filament Resource (6-8 hours)

#### Task 2.1: Create Base Resource
- [ ] Create `app/Filament/Resources/WarrantyClaimResource.php`
  - Navigation: 'Sales' group
  - Icon: 'heroicon-o-shield-check'
  - Label: 'Warranty Claims'
  - Eager load: customer, warehouse, items, histories

#### Task 2.2: Create Form Schema
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Schemas/WarrantyClaimForm.php`
  - Section: Claim Information
    - customer_id (select, searchable)
    - invoice_id (select, searchable) ⭐ NEW - Link to existing invoice
      - Auto-populate customer when invoice selected
      - Show invoice details (number, date, total)
      - Optional field (can claim without invoice)
    - warehouse_id (select)
    - representative_id (select, default: current user)
    - issue_date (date picker)
    - claim_date (date picker)
  
  - Section: Claimed Items (Repeater)
    - **Option A: Manual Entry**
      - product_variant_id (select with search)
      - quantity (number)
      - issue_description (textarea)
      - resolution_action (select: replace/refund/repair)
    
    - **Option B: From Invoice (if invoice selected)** ⭐ NEW
      - "Import from Invoice" button
      - Shows invoice items in dropdown
      - Auto-fills product, quantity
      - User adds issue_description
  
  - Section: Notes
    - notes (textarea - customer-facing)
    - internal_notes (textarea - internal only)

#### Task 2.3: Create Table Schema
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Tables/WarrantyClaimsTable.php`
  - Columns:
    - claim_date (date, sortable)
    - claim_number (searchable)
    - customer.business_name (searchable)
    - status (badge with colors: blue/yellow/green/red)
    - items_count (count of items)
  - Filters:
    - SelectFilter: status
    - SelectFilter: warehouse
    - DateFilter: claim_date range
  - Actions:
    - ViewAction (with tooltip: "View claim details")
    - EditAction (with tooltip: "Edit claim")
    - DeleteAction (with tooltip: "Delete claim")
  - Bulk Actions:
    - Export selected
    - Mark as Replaced
    - Mark as Claimed
    - Send notification

#### Task 2.4: Create Infolist (View) Schema
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Schemas/WarrantyClaimInfolist.php`
  - Section: Claim Details
    - claim_number, status badge, dates
    - customer info, warehouse info
  
  - Section: Claimed Items (table)
    - Product, Quantity, Issue Description, Resolution
  
  - Section: **Claim History (Scrollable Timeline)** ⭐ KEY FEATURE
    - Show history entries in reverse chronological order
    - Each entry: date, user, action type, description
    - Video links should be clickable
    - File attachments should be downloadable
    - Color-coded by action type

---

### Phase 3: Custom Actions (4-5 hours)

#### Task 3.1: Create Action Classes
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/AddNoteAction.php`
  - Modal with textarea
  - Save to warranty_claim_history
  - Notification on success
  - Refresh claim history

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/AddVideoLinkAction.php`
  - Modal with URL input
  - Validate URL (Google Drive, YouTube, Vimeo, etc.)
  - Save to warranty_claim_history with metadata
  - Display clickable link in history

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/MarkAsReplacedAction.php`
  - Change status to 'replaced'
  - Optional: Create replacement order/invoice
  - Log to history
  - Send email notification

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/MarkAsClaimedAction.php`
  - Change status to 'claimed'
  - Set resolution_date
  - Log to history
  - Send email notification

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Actions/VoidClaimAction.php`
  - Change status to 'void'
  - Require void reason
  - Log to history
  - Cannot be undone (confirmation required)

#### Task 3.2: Add Tooltips to All Actions
- [ ] Copy tooltip pattern from InvoiceResource
- [ ] Add tooltips for:
  - 👁️ View Claim: "View complete claim details and history"
  - ✏️ Edit: "Modify claim information"
  - 📝 Add Note: "Add internal or customer-facing note"
  - 🎥 Add Video Link: "Add video evidence link (Google Drive, YouTube)"
  - ✅ Mark as Replaced: "Mark items as replaced and close claim"
  - ✅ Mark as Claimed: "Mark claim as valid and processed"
  - 🚫 Void: "Cancel claim (cannot be undone)"
  - 📄 Print/Download: "Generate PDF report"
  - 📧 Send: "Send claim update email to customer"
  - 📎 Attachments: "Upload supporting documents"
  - 🗑️ Delete: "Permanently delete claim"

---

### Phase 4: Pages (2-3 hours)

#### Task 4.1: Create Page Classes
- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/ListWarrantyClaims.php`
  - Header actions: Create new claim
  - Table with filters and actions
  - Bulk actions

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/CreateWarrantyClaim.php`
  - Form with validation
  - Auto-generate claim_number
  - Set created_by to current user
  - Redirect to view page on success

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/EditWarrantyClaim.php`
  - Form with existing data
  - Disable claim_number field
  - Log changes to history

- [ ] Create `app/Filament/Resources/WarrantyClaimResource/Pages/ViewWarrantyClaim.php`
  - Infolist with all details
  - **Scrollable claim history section** (key feature)
  - Header actions:
    - Add Note
    - Add Video Link
    - Mark as Replaced
    - Mark as Claimed
    - Void
    - Print/Download
    - Send Email
    - Edit
    - Delete
  - Sidebar: Attachments widget (count + list)

---

### Phase 5: Services (3-4 hours)

#### Task 5.1: Create Service Classes
- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimService.php`
  - Method: `createClaim(array $data): WarrantyClaim`
  - Method: `updateClaim(WarrantyClaim $claim, array $data): WarrantyClaim`
  - Method: `addNote(WarrantyClaim $claim, string $note): void`
  - Method: `addVideoLink(WarrantyClaim $claim, string $url): void`
  - Method: `markAsReplaced(WarrantyClaim $claim, ?string $notes): void`
  - Method: `markAsClaimed(WarrantyClaim $claim, ?string $notes): void`
  - Method: `voidClaim(WarrantyClaim $claim, string $reason): void`
  - Method: `generateClaimNumber(): string` (format: "2392130")

- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimHistoryService.php`
  - Method: `logAction(WarrantyClaim $claim, ClaimActionType $type, string $description, ?array $metadata): void`
  - Method: `getHistoryForClaim(WarrantyClaim $claim): Collection`
  - Method: `formatHistoryEntry(WarrantyClaimHistory $history): string`

---

### Phase 6: PDF & Email (4-5 hours)

#### Task 6.1: PDF Generation
- [ ] Install Laravel DomPDF: `composer require barryvdh/laravel-dompdf`
- [ ] Create `resources/views/pdfs/warranty_claim.blade.php`
  - Header: Company branding
  - Claim details
  - Customer details
  - Items table
  - History timeline
  - Footer: Terms & conditions

- [ ] Create `app/Modules/Warranties/Services/WarrantyClaimPdfService.php`
  - Method: `generate(WarrantyClaim $claim): \Barryvdh\DomPDF\PDF`
  - Method: `download(WarrantyClaim $claim): Response`
  - Method: `stream(WarrantyClaim $claim): Response`

#### Task 6.2: Email Notifications
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimCreated.php`
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimUpdated.php`
- [ ] Create `app/Modules/Warranties/Mail/WarrantyClaimResolved.php`
- [ ] Create email templates in `resources/views/emails/warranties/`

---

### Phase 7: Testing (4-5 hours)

#### Task 7.1: Create Test Suite
- [ ] Create `test_warranty_claim_actions.php`
  - Test: Create claim
  - Test: Add note
  - Test: Add video link
  - Test: Mark as replaced
  - Test: Mark as claimed
  - Test: Void claim
  - Test: Generate PDF
  - Test: Send email

- [ ] Create `test_warranty_claim_history.php`
  - Test: History logging
  - Test: History retrieval
  - Test: History formatting

- [ ] Create `test_warranty_claim_statuses.php`
  - Test: Status transitions (draft → pending → replaced)
  - Test: Invalid status transitions
  - Test: Status badge colors

- [ ] Create `test_all_warranty_actions.php`
  - Master test runner
  - Run all warranty tests
  - Generate report

#### Task 7.2: Create Test Data Seeder
- [ ] Create `database/seeders/WarrantyClaimSeeder.php`
  - Seed 10-15 warranty claims with different statuses
  - Include items, history entries, notes, video links
  - Link to existing customers and products

---

### Phase 8: Documentation (2-3 hours)

#### Task 8.1: Create Documentation Files
- [ ] Create `docs/architecture/ARCHITECTURE_WARRANTY_MODULE.md`
  - Module overview
  - Database schema
  - Relationships diagram
  - Business logic explanation
  - Status workflow diagram

- [ ] Create `WARRANTY_CLAIMS_IMPLEMENTATION.md`
  - Implementation steps completed
  - Features list
  - Screenshots
  - Usage guide

- [ ] Create `WARRANTY_CLAIMS_TESTS_README.md`
  - Test suite documentation
  - How to run tests
  - Expected results
  - Troubleshooting

- [ ] Create `WARRANTY_CLAIMS_USER_GUIDE.md`
  - How to create a claim
  - How to add notes/videos
  - How to mark as replaced/claimed
  - Email notification settings

---

---

## 🎨 UI/UX RECOMMENDATIONS (Better than Scrollable Timeline)

### Client Vision vs Best Practice

**Client showed:** Scrollable timeline (limited vertical space)

**Better UX Options:**

### ✅ **OPTION 1: Filament Timeline Component (RECOMMENDED)**
**Why it's better:**
- Built-in Filament component (less code)
- Responsive and accessible
- Auto-pagination (better than scrolling)
- Professional look
- Easy to maintain

**Implementation:**
```php
// In Infolist
Section::make('Claim History')
    ->schema([
        ViewEntry::make('histories')
            ->view('filament.warranty-claims.history-timeline')
    ])
```

**Features:**
- Show latest 10 entries by default
- "Load More" button at bottom
- Icons for each action type (📝 note, 🎥 video, ✅ status change)
- User avatar + name
- Relative timestamps (2 hours ago, yesterday)
- Expandable metadata (click to see full details)

---

### ✅ **OPTION 2: Accordion History (Good for Mobile)**
**Why it's good:**
- Each history entry is a collapsible accordion
- Click to expand and see full details
- Saves vertical space
- Great for mobile devices

**Example:**
```
▼ 2025-04-10 - Note added by John Doe (2 days ago)
  "Damage piece not returned, replacement issued."
  
▶ 2025-04-09 - Video link added by Jane Smith (3 days ago)

▶ 2025-04-08 - Status changed by Admin (4 days ago)
```

---

### ✅ **OPTION 3: Tabbed History (Most Organized)**
**Why it's excellent:**
- Separate tabs for different action types
- Users can quickly find specific info
- Less overwhelming
- Professional appearance

**Tabs:**
- **All** - Show everything
- **Notes** - Only notes
- **Videos** - Only video links
- **Status Changes** - Only status updates
- **Attachments** - Only files

---

### ✅ **OPTION 4: Infinite Scroll (Modern, Smooth)**
**Why it's modern:**
- No "Load More" button
- Auto-loads as you scroll down
- Smooth user experience
- Popular in modern apps

**Technical:**
- Use Livewire pagination with infinite scroll
- Load 15 entries at a time
- Show loading spinner while fetching

---

### 🏆 **MY RECOMMENDATION: Combination Approach**

**Best of all worlds:**

1. **Main View: Filament Timeline** (Option 1)
   - Show latest 5-10 entries
   - Professional, clean look
   - Icons for visual clarity

2. **Modal for Full History**
   - Click "View Full History" button
   - Opens modal with infinite scroll
   - Filter by action type
   - Search history

3. **Quick Actions Widget**
   - Separate widget for quick actions
   - "Add Note" and "Add Video Link" always visible
   - Don't mix actions with history

**Example Layout:**
```
┌─────────────────────────────────────────────────────┐
│  Warranty Claim #2392130                  [Edit] [•]│
├─────────────────────────────────────────────────────┤
│                                                      │
│  Recent Activity (Timeline)                         │
│  ┌────────────────────────────────────────────┐    │
│  │ 📝 Note added - 2 hours ago                │    │
│  │    by John Doe                              │    │
│  │    "Replacement shipped via FedEx"          │    │
│  │                                              │    │
│  │ 🎥 Video link added - Yesterday             │    │
│  │    by Jane Smith                             │    │
│  │    [View Video] googledrive.com/...         │    │
│  │                                              │    │
│  │ ✅ Status changed - 2 days ago              │    │
│  │    by Admin                                  │    │
│  │    pending → replaced                        │    │
│  └────────────────────────────────────────────┘    │
│                                                      │
│  [View Full History (28 entries)]                   │
│                                                      │
├─────────────────────────────────────────────────────┤
│  Quick Actions                                       │
│  [📝 Add Note]  [🎥 Add Video Link]  [📎 Attach]    │
└─────────────────────────────────────────────────────┘
```

---

### 💡 Additional UX Enhancements

#### 1. **Smart History Grouping**
Group history by date:
```
Today
  📝 Note added - 2 hours ago
  🎥 Video link added - 4 hours ago

Yesterday
  ✅ Status changed to Replaced
  📧 Email sent to customer

April 8, 2025
  📝 Note added - Claim received
```

#### 2. **Action Icons with Colors**
```php
'note_added' => ['icon' => 'heroicon-o-pencil', 'color' => 'gray'],
'video_link_added' => ['icon' => 'heroicon-o-video-camera', 'color' => 'blue'],
'status_changed' => ['icon' => 'heroicon-o-arrow-path', 'color' => 'green'],
'file_attached' => ['icon' => 'heroicon-o-paper-clip', 'color' => 'purple'],
'email_sent' => ['icon' => 'heroicon-o-envelope', 'color' => 'orange'],
```

#### 3. **Inline Actions**
Allow quick actions on history entries:
- Reply to a note
- Edit your own notes
- Delete your own entries
- Copy video link

#### 4. **Rich Metadata Display**
When video link added, show thumbnail:
```
🎥 Video link added - 2 hours ago
   by John Doe
   
   [Video Thumbnail Preview]
   googledrive.com/392478479273
   [Watch Video] [Copy Link]
```

#### 5. **Activity Stats Widget**
Show summary at top:
```
┌─────────────────────────────────────┐
│  Claim Activity Summary              │
│  📝 12 Notes  🎥 3 Videos  📎 5 Files│
│  ✅ 4 Status Changes  📧 6 Emails    │
└─────────────────────────────────────┘
```

---

## 🎯 FINAL RECOMMENDATION

**Implement this structure:**

### View Page Layout:
```php
// Top Section
- Claim Details (customer, invoice link, status)
- Quick Stats Widget
- Quick Actions (Add Note, Add Video, Attach File)

// Middle Section  
- Claimed Items Table
- Invoice Reference (if linked)

// Bottom Section
- Activity Timeline (Filament Timeline Component)
  - Latest 5 entries shown
  - Grouped by date
  - Icons + colors
  - User avatars
  - "View Full History" button
  
// Full History Modal (when clicked)
- Infinite scroll
- Filter by type
- Search capability
- Export to PDF
```

**Benefits:**
- ✅ No scrolling issues (pagination handles it)
- ✅ Professional appearance (matches Filament design)
- ✅ Easy to find information (grouped, filtered)
- ✅ Mobile friendly (responsive)
- ✅ Fast loading (lazy load history)
- ✅ Accessible (keyboard navigation)

---

## 🎨 UI/UX REQUIREMENTS (Based on Mock-ups)

### Color Scheme (Status Badges)
```php
'draft' => 'gray',      // Draft claims
'pending' => 'warning', // Awaiting review
'replaced' => 'warning', // Yellow - Replacement issued
'claimed' => 'success', // Green - Claim approved
'returned' => 'info',   // Blue - Item returned
'void' => 'danger',     // Red - Voided
```

### Claim History Timeline
- Scrollable container (max-height: 400px)
- Entries in reverse chronological order (newest first)
- Each entry format:
  ```
  2025-04-09
  Link added: googledrive.com/392478479273 [clickable]
  
  2025-04-10
  Note added: Damage piece not returned, replacement issued.
  ```
- Color-coded icons by action type:
  - 📝 Note added (gray)
  - 🎥 Video link added (blue)
  - ✅ Status changed (green)
  - 📎 File attached (purple)
  - 📧 Email sent (orange)

### Modal Design
- Clean, centered modal
- Large text area (min 200px height)
- Submit button (primary color)
- Cancel button (secondary color)
- Close X button (top right)

---

## 📊 ESTIMATED TIME BREAKDOWN

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| Phase 1: Database & Models | 8 tasks | 4-5 hours |
| Phase 2: Filament Resource | 4 tasks | 6-8 hours |
| Phase 3: Custom Actions | 2 tasks | 4-5 hours |
| Phase 4: Pages | 1 task | 2-3 hours |
| Phase 5: Services | 2 tasks | 3-4 hours |
| Phase 6: PDF & Email | 2 tasks | 4-5 hours |
| Phase 7: Testing | 2 tasks | 4-5 hours |
| Phase 8: Documentation | 1 task | 2-3 hours |
| **TOTAL** | **22 tasks** | **29-38 hours** |

**Realistic Estimate:** ~35 hours (1 week of full-time work)

---

## 🎯 SUCCESS CRITERIA

### Functional Requirements
- [ ] Can create warranty claims with customer and product info
- [ ] Can add notes to claims (visible in history)
- [ ] Can add video links to claims (clickable in history)
- [ ] Can upload file attachments
- [ ] Can mark claims as Replaced/Claimed
- [ ] Can void claims with reason
- [ ] History timeline shows all actions chronologically
- [ ] Status badges display correctly with colors
- [ ] PDF generation works
- [ ] Email notifications send successfully

### UI/UX Requirements
- [ ] Matches mock-up design
- [ ] Scrollable history section
- [ ] Action tooltips helpful and clear
- [ ] Modals clean and functional
- [ ] Sidebar shows attachments count
- [ ] Table sortable and filterable

### Testing Requirements
- [ ] All test scripts pass (100%)
- [ ] 10+ warranty claims seeded
- [ ] History logging verified
- [ ] Status transitions validated

---

## 🔄 INTEGRATION POINTS

### With Existing Modules
1. **Customers Module** - Link claims to customers
2. **Products Module** - Link claimed items to products
3. **Orders Module** - Reference original order for warranty validation
4. **Warehouse Module** - Track which warehouse handles replacement
5. **Inventory Module** - Allocate replacement items from inventory
6. **Users Module** - Track who created/resolved claims

### New Dependencies
- Laravel DomPDF (for PDF generation)
- Email templates (for notifications)

---

## 📝 QUESTIONS FOR CLARIFICATION

### Business Logic Questions
1. **Warranty Period:** Do products have warranty duration (e.g., 1 year, 2 years)?
2. **Automatic Validation:** Should system check if claim is within warranty period?
3. **Inventory Impact:** When marking as "Replaced", should system:
   - Automatically create a new order/invoice?
   - Allocate replacement items from inventory?
   - Track original item as returned?
4. **Multiple Items:** Can one claim have multiple products/items?
5. **Claim Approval:** Is there an approval workflow (draft → pending → approved → resolved)?
6. **Notifications:** Who receives emails? (Customer, Sales Rep, Warehouse Manager?)
7. **Return Tracking:** Should system track physical return of defective items?

### Technical Questions
1. **Video Links:** Support only Google Drive or also YouTube, Vimeo, etc.?
2. **File Attachments:** Max file size? Allowed types? Storage location (S3, local)?
3. **Claim Number Format:** Use auto-increment (2392130) or custom format (WC-2025-0001)?
4. **History Retention:** Keep all history forever or auto-delete after X months?
5. **PDF Template:** Same branding as invoices or custom warranty claim template?

---

## 🚀 READY TO START

### Recommended Approach
1. **Start with Phase 1** (Database & Models) - Get foundation right
2. **Then Phase 2** (Filament Resource) - Build the UI
3. **Then Phase 3** (Custom Actions) - Add interactivity
4. **Test as you go** - Don't wait until Phase 7

### Quick Start Checklist
- [ ] Create `app/Modules/Warranties` directory
- [ ] Create enums folder: `app/Modules/Warranties/Enums`
- [ ] Create models folder: `app/Modules/Warranties/Models`
- [ ] Create migrations: `database/migrations/*warranty*`
- [ ] Copy ConsignmentResource.php as template
- [ ] Modify for warranty claims structure

---

**Ready to answer questions and start implementation!** 🎯

Let me know:
1. Answers to business logic questions above
2. Any additional features needed
3. Priority level (high/medium/low)
4. Target completion date
