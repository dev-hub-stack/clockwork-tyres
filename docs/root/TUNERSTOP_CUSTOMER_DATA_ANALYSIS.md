# TunerStop Historical Customer Data Analysis

**Date**: December 14, 2025  
**Purpose**: Map TunerStop customers → CRM as retail customers

---

## 📊 Data Summary

### Users (Registered Customers)
- **Total users**: 1,404
- **Users with orders**: 196 (14%)
- **Guest orders**: ~80% of total orders

### Order-Related Data
- **Total orders**: 3,253 (from billing/shipping count)
- **Billing records**: 3,253 (one per order)
- **Shipping records**: 3,253 (one per order)
- **Address books**: 55 (saved addresses for registered users)

---

## 📋 Table Structures

### 1. **users** table
Contains registered customer accounts (196 with orders, 1,404 total)

**Key Fields**:
```
- id
- name (full name)
- email
- phone (mostly NULL)
- address (mostly NULL)
- city (mostly NULL)
- country (mostly NULL)
- created_at
```

**Issue**: Most users don't have address/phone filled in their profile.

---

### 2. **billing** table
One record per order - contains customer info at time of purchase

**Structure**:
```json
{
  "id": 76,
  "first_name": "Claudio Gilberto",
  "last_name": "Conceição",
  "country": "UAE",
  "city": "Dubai",
  "address": "Al Wasl road villa 15...",
  "phone": "+971585945747",
  "email": "claudiogilberto22@gmail.com",
  "order_id": 58,
  "is_same_shipping": 1,
  "created_at": "2020-10-22 07:46:53"
}
```

**✅ Best source for customer data** - has complete info for EVERY order

---

### 3. **shipping** table
One record per order - delivery address

**Structure**: Same as billing (first_name, last_name, country, city, address, phone, email, order_id)

---

### 4. **address_books** table
Saved addresses for registered users (only 55 records)

**Note**: Not useful for guest orders, but should be preserved for registered users.

---

## 🎯 Import Strategy

### Phase 1: Create Customers from Billing Data

**Logic**:
1. **Group billing records by email** → unique customers
2. For each unique email:
   - Extract first_name, last_name, phone, email
   - Use most recent record's data
   - Create as `customer_type = 'retail'`
   - Mark as `external_source = 'tunerstop_historical'`

**Expected Result**: ~850-1,200 unique customers (estimate based on orders)

### Phase 2: Link to Registered Users

For customers that exist in `users` table:
- Add `external_user_id` = user.id
- Preserve user registration date

### Phase 3: Import Addresses

For each customer:
1. **Billing Address**: From `billing` table (first occurrence)
   - Label: "Billing" or "Primary"
   
2. **Shipping Address**: From `shipping` table if different
   - Label: "Shipping" or "Delivery"
   
3. **Saved Addresses**: From `address_books` for registered users
   - Label: "Saved Address {n}"

### Phase 4: Link Orders to Customers

Match orders to customers by:
- `billing.email` → `customers.email`
- Update `orders.customer_id`

---

## 🔄 Data Quality Considerations

### Duplicates
- **Same email, different names**: Use most recent
- **Same email, typos**: Manual cleanup later
- **NULL emails**: Create as "Guest Customer #{order_id}"

### Missing Data
- **No phone**: Leave NULL
- **No city/country**: Default to "UAE" (primary market)
- **Invalid email**: Use `guest-{order_id}@tunerstop.local`

### Privacy
- **Mark historical**: Add flag `is_historical_import = true`
- **Verify before contact**: Add note in customer record

---

## 📐 CRM Customer Schema Match

**TunerStop** → **CRM**:
```
billing.first_name       → customers.first_name
billing.last_name        → customers.last_name
billing.email            → customers.email
billing.phone            → customers.phone
'retail'                 → customers.customer_type
billing.country          → customers.country (if exists)
billing.city             → customers.city (if exists)
billing.created_at       → customers.created_at (earliest order date)
```

**Addresses**:
```
billing.*                → customer_addresses (type: 'billing')
shipping.*               → customer_addresses (type: 'shipping')
address_books.*          → customer_addresses (type: 'saved')
```

---

## 🚀 Implementation Plan

### Step 1: Extract Unique Customers
```sql
SELECT 
    email,
    first_name,
    last_name,
    phone,
    country,
    city,
    MIN(created_at) as customer_since,
    COUNT(*) as order_count
FROM billing
WHERE email IS NOT NULL
GROUP BY email
ORDER BY customer_since
```

### Step 2: Import Customers
- Create in `customers` table
- Set `customer_type = 'retail'`
- Set `external_source = 'tunerstop_historical'`
- Set `is_active = true`
- Add note: "Imported from TunerStop historical data"

### Step 3: Import Addresses
- Create billing address (primary)
- Create shipping address (if different)
- Link to customer via `customer_id`

### Step 4: Update Orders
- Match order → customer by email
- Update `orders.customer_id`

---

## 📊 Expected Results

- **Customers**: ~850-1,200 retail customers
- **Addresses**: ~1,500-2,500 addresses (billing + shipping)
- **Orders with customers**: 3,253 orders linked to actual customers
- **Customer lifetime value**: Calculable per customer
- **Repeat customers**: Identifiable

---

## ✅ Benefits

1. **Rich Reports**:
   - Top customers by revenue
   - Customer acquisition trends
   - Geographic distribution
   - Repeat purchase rates

2. **Marketing**:
   - Email lists for campaigns
   - Customer segmentation
   - Churn analysis

3. **Business Intelligence**:
   - Customer lifetime value (CLV)
   - Average order value per customer
   - Customer retention rates
   - New vs returning customer trends

---

## ⚠️ Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Duplicate emails | Group by email, use most recent data |
| Invalid emails | Create guest accounts with generated emails |
| Privacy concerns | Mark as historical, add verification flag |
| Incomplete data | Use defaults, mark fields as "needs verification" |
| Long import time | Use batch processing, show progress |

---

## 🎯 Ready to Implement

**Estimated import time**: 10-15 minutes  
**Estimated unique customers**: ~1,000  
**Data quality**: Good (billing/shipping has complete info)  

Shall I proceed with implementation?
