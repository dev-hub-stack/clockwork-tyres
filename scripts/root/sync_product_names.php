<?php
// Quick script to sync product names from tunerstop DB into reporting_crm
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=reporting_crm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Count how many will be updated
$count = $pdo->query("
    SELECT COUNT(*) FROM reporting_crm.products p
    INNER JOIN tunerstop.products tp ON tp.id = p.id
    WHERE (p.name IS NULL OR p.name = '')
      AND tp.product_full_name IS NOT NULL
      AND tp.product_full_name != ''
")->fetchColumn();

echo "Products to update: $count\n";

// Run the update
$updated = $pdo->exec("
    UPDATE reporting_crm.products p
    INNER JOIN tunerstop.products tp ON tp.id = p.id
    SET p.name = tp.product_full_name
    WHERE (p.name IS NULL OR p.name = '')
      AND tp.product_full_name IS NOT NULL
      AND tp.product_full_name != ''
");

echo "Updated: $updated rows\n";

// Also update brand_id, model_id, finish_id where they are NULL
$updated2 = $pdo->exec("
    UPDATE reporting_crm.products p
    INNER JOIN tunerstop.products tp ON tp.id = p.id
    SET
        p.name      = COALESCE(NULLIF(p.name, ''), tp.product_full_name),
        p.brand_id  = COALESCE(p.brand_id, tp.brand_id),
        p.model_id  = COALESCE(p.model_id, tp.model_id),
        p.finish_id = COALESCE(p.finish_id, tp.finish_id)
    WHERE tp.id IS NOT NULL
");

echo "Full sync updated: $updated2 rows\n";
echo "Done!\n";
