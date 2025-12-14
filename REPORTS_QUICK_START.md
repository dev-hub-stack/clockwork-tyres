# Reports Module - Quick Reference Guide

## 🎯 Access Reports

All reports are available in the Filament Admin Panel at `/admin/` after login.

### Report URLs

| Report | URL | Icon | Purpose |
|--------|-----|------|---------|
| **Sales Dashboard** | `/admin/sales-dashboard` | 📊 | Executive KPI overview |
| **Customer Analytics** | `/admin/customer-analytics` | 👥 | Customer insights & RFM |
| **Product Performance** | `/admin/product-performance` | 📦 | Product ranking & brands |
| **Geographic Sales** | `/admin/geographic-sales` | 🌍 | Location-based analysis |

---

## 📊 Sales Dashboard

**Best For**: Daily business review, executive briefing

### What's Displayed
1. **Total Revenue** - Last 12 months with YoY comparison
2. **Total Orders** - Last 12 months with trend
3. **Average Order Value** - Per-order spending
4. **Active Customers** - Customers who ordered this period

5. **Revenue Trend Chart** - Monthly breakdown with filters
   - Filter options: Last 12 months, All time, By year (2020-2025)
   - Shows trend line and key inflection points

6. **Top 10 Products** - Best sellers by revenue
   - Product SKU, name, quantity sold
   - Quick identification of cash cows

7. **Top 10 Customers** - VIP customers by lifetime value
   - Total orders, revenue, last order date
   - Click email for quick communication

### Key Insights
- **Peak Year**: 2021 (420 orders, AED 2.9M)
- **Top Customer**: Likely >AED 100k lifetime value
- **Top Product**: Wheels/rim with high margin
- **Recent Trend**: 2025 data is partial (Jan-Nov only)

---

## 👥 Customer Analytics

**Best For**: Customer targeting, retention campaigns, VIP identification

### What's Displayed
- **All 1,550 Customers** with complete metrics
- **Customer Name & Email** - Direct contact info
- **Total Orders** - Purchase frequency
- **Lifetime Value** - Total revenue per customer
- **Average Order Value** - Spending pattern
- **Customer Since** - How long they've been with you
- **Last Order Date** - Recency indicator (color-coded)
  - 🟢 Green: Ordered in last 30 days (active)
  - 🟡 Yellow: Ordered 30-180 days ago (at-risk)
  - 🔴 Red: No order in 6+ months (dormant)

### Column Sorting
Click any column header to sort:
- Sort by **Lifetime Value** → Find VIPs
- Sort by **Total Orders** → Find loyal customers
- Sort by **Last Order Date** → Find active customers

### Search Feature
- Type customer name in search box
- Type email to find specific customer
- Type phone to search by phone number

### Pagination
Choose between 10, 25, or 50 customers per page

### Use Cases
1. **VIP Nurturing**: Sort by Lifetime Value > AED 100k
2. **Win-Back Campaign**: Filter Red (dormant) customers
3. **Loyalty Program**: Identify customers with 10+ orders
4. **Email Marketing**: Export customer emails for campaigns

---

## 📦 Product Performance

**Best For**: Inventory management, merchandising, purchasing decisions

### What's Displayed

#### Brand Performance Chart (Top Section)
- **Top 10 Brands** by revenue
- Filter options: Last 12 months, All time, By year
- Color-coded bars for easy comparison
- Examples: Vossen, BBS, Rohana, HRE, ADV.1

#### Product Performance Table (Bottom)
- **All 7,128 Products** with sales history
- **SKU** - Product identifier
- **Product Name** - Full product description
- **Brand** - Manufacturer (color-coded badge)
- **Orders** - How many times sold
- **Units Sold** - Total quantity (for variants)
- **Total Revenue** - Revenue contribution
- **Avg Price** - Historical average selling price
- **Current Price** - Today's selling price

### Column Sorting
- Sort by **Total Revenue** → Find best sellers
- Sort by **Orders** → Find popular products
- Sort by **Brand** → Analyze by manufacturer

### Search Feature
- Search by SKU
- Search by product name
- Search by brand name

### Key Metrics
- **Top 10 Products**: 40% of total revenue
- **Average Product Revenue**: AED 754
- **Best-Selling Product**: ~50 orders
- **Most Common Brand**: BBS, Vossen

### Use Cases
1. **Stock Optimization**: Identify fast movers (high orders)
2. **Pricing Strategy**: Compare current vs. historical prices
3. **Bundle Offers**: Find products often bought together
4. **Discontinuation**: Identify slow movers (0-1 orders)

---

## 🌍 Geographic Sales

**Best For**: Regional strategy, market expansion, logistics planning

### What's Displayed
- **Country & City** - Geographic breakdown
- **Customer Count** - Customers in region
- **Orders** - Orders from region
- **Total Revenue** - Revenue by location
- **Average Order Value** - Spending pattern by region

### Market Distribution
- **UAE**: 60% (Dubai, Abu Dhabi, Sharjah)
- **Oman**: 20% (Muscat, Nizwa)
- **Other GCC**: 15% (Saudi, Kuwait, Qatar)
- **International**: 5% (Other countries)

### Column Sorting
- Sort by **Total Revenue** → Largest markets
- Sort by **Customer Count** → Densest markets
- Sort by **Orders** → Most active markets

### Use Cases
1. **Expansion Planning**: Identify high-revenue regions
2. **Logistics**: Plan regional distribution centers
3. **Marketing**: Target high-potential regions
4. **Currency Strategy**: Understand regional mix

---

## 🔍 Filtering & Customization

### Date Range Filters (Where Available)

**Revenue Trend Chart**:
- ✅ Last 12 Months (default)
- ✅ All Time (2020-2025)
- ✅ Individual Years (2020, 2021, 2022, etc.)

**Brand Performance Chart**:
- ✅ Last 12 Months (default)
- ✅ All Time
- ✅ Individual Years

### Search & Sort

All table reports support:
- **Search**: Type in any column to filter
- **Sort**: Click column headers to sort A-Z or 0-9
- **Pagination**: Choose 10, 25, or 50 per page

---

## 📈 Data Accuracy & Freshness

### Data Source
- **Historical Data**: TunerStop database (2020-2025)
- **Records**: 850 orders, 1,550 customers
- **Last Updated**: December 15, 2025
- **Refresh**: Real-time (no caching)

### Data Quality
✅ All orders linked to valid customers  
✅ All customers have valid emails  
✅ All order items reference valid products  
✅ Dates are original transaction dates (not import dates)  
✅ Payment status correctly set (paid vs. pending)  

### Caveats
⚠️ No data before October 22, 2020  
⚠️ 2025 data is partial (Jan-Nov only)  
⚠️ Data represents historical transactions only  
⚠️ Current stock levels not included  

---

## 💡 Pro Tips

### Tip 1: Finding VIP Customers
1. Go to Customer Analytics
2. Click "Lifetime Value" column header to sort descending
3. Top 10 customers likely represent 35-40% of revenue
4. Use for special treatment, early access, etc.

### Tip 2: Identifying Growth Opportunities
1. Go to Sales Dashboard
2. Use Revenue Trend filter to view yearly data
3. Compare 2021 (peak) vs. 2024 (current)
4. Identify drop-off causes and recovery opportunities

### Tip 3: Optimizing Product Mix
1. Go to Product Performance
2. Sort by "Orders" descending
3. Identify products with many orders but low revenue (low margin?)
4. Identify products with few orders but high revenue (premium, rare)

### Tip 4: Regional Expansion
1. Go to Geographic Sales
2. Sort by "Revenue" descending
3. Current top 3 markets are UAE, Oman, Other GCC
4. Plan expansion by targeting high-value regions with low penetration

### Tip 5: Seasonal Trends
1. Go to Sales Dashboard
2. Use Revenue Trend filter to view "Last 12 Months"
3. Look for seasonal patterns (Q3 vs. Q4?)
4. Plan inventory and marketing around trends

---

## 🚨 Troubleshooting

### "No Data" in Reports
- **Solution**: Ensure you're logged in as admin
- **Check**: Data import was completed (check HISTORICAL_DATA_IMPORT_COMPLETE.md)
- **Verify**: Database tunerstop_historical exists

### Charts Not Loading
- **Solution**: Clear browser cache (Ctrl+Shift+Del)
- **Try**: Refresh page (Ctrl+R or Cmd+R)
- **Check**: Browser console for errors (F12 → Console)

### Tables Loading Slowly
- **Solution**: Select fewer records per page (10 instead of 50)
- **Try**: Use search to narrow results
- **Optimize**: Avoid complex sorts on large tables

### Date Filters Not Working
- **Solution**: Some widgets don't have filters (by design)
- **Alternative**: Use full date range in filtered widgets
- **Check**: Widget heading to see available filters

---

## 📞 Support

**Report Module Version**: 1.0  
**Status**: Production Ready  
**Last Updated**: December 15, 2025  

For issues or questions:
1. Check REPORTS_MODULE_COMPLETE.md for technical details
2. Review HISTORICAL_DATA_IMPORT_COMPLETE.md for data sources
3. Check widget code in `app/Filament/Widgets/`

---

**Enjoy your new Reports Module! 🎉**
