<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Wholesale VAT Rate
    |--------------------------------------------------------------------------
    | Applied to (sub_total - discount + shipping) when calculateVat() is called.
    | Default: 5% (UAE standard VAT)
    */
    'vat_rate' => env('WHOLESALE_VAT_RATE', 0.05),

    /*
    |--------------------------------------------------------------------------
    | Shipping Rates (AED)
    |--------------------------------------------------------------------------
    | Flat rates per shipping method. Used in CartService::calculateShipping().
    | Add new methods here without touching code.
    */
    'shipping_rates' => [
        'standard' => 50.00,
        'express'  => 100.00,
        'DHL'      => 150.00,
        'free'     => 0.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Shipping Threshold (AED)
    |--------------------------------------------------------------------------
    | Orders with sub_total above this amount qualify for free shipping.
    */
    'free_shipping_threshold' => env('WHOLESALE_FREE_SHIPPING_THRESHOLD', 5000.00),

    /*
    |--------------------------------------------------------------------------
    | Available Payment Gateways
    |--------------------------------------------------------------------------
    | List returned to Angular via GET /api/checkout-options
    */
    'payment_gateways' => [
        ['id' => 'Stripe',  'name' => 'Credit / Debit Card',  'icon' => 'credit-card'],
        ['id' => 'PostPay', 'name' => 'PostPay (Buy Now Pay Later)', 'icon' => 'postpay'],
        ['id' => 'BankTransfer', 'name' => 'Bank Transfer', 'icon' => 'bank'],
    ],

];
