# Wholesale Pricing Strategy

## Price Hierarchy

There are three price values that can exist on any product variant:

| Field | Meaning |
|---|---|
| `uae_retail_price` (MSRP) | The full list price. Always present. |
| `sale_price` | A temporary promotional price set manually. Optional. |
| Dealer discount | Per-customer % or fixed amount off MSRP, set per brand or per model. Optional. |

---

## The Rule: Dealer % Always Applies to the Active Price

The dealer's personal discount is applied to the **sale price** when one is active, otherwise to **MSRP**. This means dealers always get their percentage off the best available base price.

```
base       = sale_price  (if set and < MSRP)
base       = MSRP        (if no sale)
your_price = base × (1 - dealer_%)
```

---

## Scenarios

### 1. No sale, no dealer discount (regular public wholesale customer)
```
MSRP:       1,470
Sale Price: —
Dealer %:   —
─────────────────
Your Price: 1,470
```

### 2. Sale price only, no personal dealer discount
```
MSRP:       1,470
Sale Price: 1,155
Dealer %:   —
─────────────────
Your Price: 1,155   ← sale price used as base
```

### 3. Dealer discount only, no active sale
```
MSRP:       1,470
Sale Price: —
Dealer %:   20%  →  1,470 × 0.80 = 1,176
─────────────────
Your Price: 1,176   ← dealer % off MSRP
```

### 4. Sale price + dealer discount (compound)
```
MSRP:       1,470
Sale Price: 1,155
Dealer %:   20%  →  1,155 × 0.80 = 924
─────────────────────────────────────────
Your Price: 924   ← dealer % applied to sale price
```

### 5. Sale price + higher dealer discount
```
MSRP:       1,470
Sale Price: 1,155
Dealer %:   25%  →  1,155 × 0.75 = 866
─────────────────────────────────────────
Your Price: 866   ← dealer % applied to sale price
```

---

## Dealer Discount Priority

When a dealer has multiple rules configured, the most specific rule wins:

| Priority | Rule Type | Example |
|---|---|---|
| 1 (Highest) | Model-specific | 20% off all Relations Race Wheels RR7-H |
| 2 (Medium) | Brand-specific | 15% off all Relations Race Wheels |
| — | No rule | Full MSRP (or sale price if applicable) |

Both model and brand discounts support two types:
- **Percentage** — e.g. 20% off
- **Fixed amount** — e.g. AED 100 off

---

## Where This Is Applied

| Location | File | When |
|---|---|---|
| Product listing API | `WholesaleProductTransformer.php` | Every listing page load |
| Product detail API | `ProductVariantController.php` | On variant load |
| Add to cart | `CartService.php` | Price locked at cart time |
| CRM Quote (auto-fill) | `QuoteResource.php` | When product line item selected |
| CRM Invoice (auto-fill) | `InvoiceResource.php` | When product line item selected |
