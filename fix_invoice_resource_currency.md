# Invoice Resource Currency Fix Plan

Based on grep results, need to fix these locations in InvoiceResource.php:

1. Line 318: ->prefix('AED') - Unit price prefix
2. Line 326: ->prefix('AED') - Discount prefix  
3. Line 338: Number::currency($total, 'AED') - Line total
4. Line 442: ->money('AED') - Table column sub_total
5. Line 447: ->money('AED') - Table column vat
6. Line 454: ->money('AED') - Table column total
7. Line 459: "Expenses: AED " - Description text
8. Line 471: ->money('AED') - Table column outstanding_amount
9. Line 567: 'currency' => 'AED' - Preview modal
10. Line 581: ->prefix('AED') - Payment amount prefix
11. Line 584: "Outstanding amount: AED " - Helper text
12. Line 636: 'Payment of AED ' - Notification body
13. Lines 714-762: Multiple ->prefix('AED') - Dashboard widgets (8 instances)
14. Line 791: 'AED ' - Revenue display
15. Line 795: 'AED ' - Expenses display

Strategy:
- Replace ->prefix('AED') with ->prefix(fn() => CurrencySetting::getBase()?->currency_symbol ?? 'AED')
- Replace ->money('AED') with ->money(fn() => CurrencySetting::getBase()?->currency_code ?? 'AED')
- Replace Number::currency($var, 'AED') with  Number::currency($var, CurrencySetting::getBase()?->currency_code ?? 'AED')
- Replace 'AED ' in strings with currencySymbol variable
- Replace 'currency' => 'AED' with 'currency' => $currency->currency_symbol
