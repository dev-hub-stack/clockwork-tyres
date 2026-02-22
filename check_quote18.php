<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
$taxRate    = $taxSetting ? floatval($taxSetting->rate) : 5;
$multiplier = 1 + ($taxRate / 100);

$quotes = \App\Modules\Orders\Models\Order::quotes()
    ->with('items')
    ->where('total', 0)
    ->whereHas('items')
    ->get();

echo "Found ".count($quotes)." quotes with 0 total but have items\n\n";

foreach ($quotes as $q) {
    $inclGross = 0.0;
    $exclNet   = 0.0;

    foreach ($q->items as $item) {
        $qty          = floatval($item->quantity ?? 0);
        $price        = floatval($item->unit_price ?? 0);
        $lineDiscount = floatval($item->discount ?? 0);
        $taxInclusive = (bool) ($item->tax_inclusive ?? true);
        $lineTotal    = ($qty * $price) - $lineDiscount;

        // Also fix the line_total stored on the item
        $item->line_total = $lineTotal;
        $item->save();

        if ($taxInclusive) {
            $inclGross += $lineTotal;
        } else {
            $exclNet += $lineTotal;
        }
    }

    $shipping = floatval($q->shipping ?? 0);
    $discount = floatval($q->discount ?? 0);

    $inclTax  = $inclGross - ($inclGross / $multiplier);
    $inclNet  = $inclGross / $multiplier;
    $exclBase = $exclNet + $shipping - $discount;
    $exclTax  = $exclBase * ($taxRate / 100);

    $newTotal    = round($inclGross + $exclBase + $exclTax, 2);
    $newSubTotal = round($inclNet  + $exclBase, 2);
    $newVat      = round($inclTax  + $exclTax,  2);

    $q->update([
        'sub_total' => $newSubTotal,
        'vat'       => $newVat,
        'total'     => $newTotal,
    ]);

    echo "Fixed {$q->quote_number}: total={$newTotal}, sub_total={$newSubTotal}, vat={$newVat}\n";
}

echo "\nDone.\n";
