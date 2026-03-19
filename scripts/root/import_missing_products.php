<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=reporting_crm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Insert all missing products from tunerstop into reporting_crm
// using same id so product_variant.product_id foreign keys stay valid
$inserted = $pdo->exec("
    INSERT INTO reporting_crm.products
        (id, external_product_id, external_source, name, price, brand_id, model_id, finish_id, construction, status, created_at, updated_at)
    SELECT DISTINCT
        tp.id,
        tp.id,
        'tunerstop',
        COALESCE(NULLIF(tp.product_full_name, ''), tp.name),
        COALESCE(tp.price, 0),
        tp.brand_id,
        tp.model_id,
        tp.finish_id,
        tp.construction,
        COALESCE(tp.status, 1),
        NOW(),
        NOW()
    FROM reporting_crm.product_variants pv
    LEFT JOIN reporting_crm.products p ON p.id = pv.product_id
    INNER JOIN tunerstop.products tp ON tp.id = pv.product_id
    WHERE p.id IS NULL
      -- include soft-deleted products from tunerstop, we just need the name
");

echo "Inserted: $inserted product rows\n";

// Verify
$remaining = $pdo->query("
    SELECT COUNT(*) FROM reporting_crm.product_variants pv
    LEFT JOIN reporting_crm.products p ON p.id = pv.product_id
    WHERE p.id IS NULL
")->fetchColumn();

echo "Orphaned variants remaining: $remaining\n";
echo "Done!\n";
