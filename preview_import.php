<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=reporting_crm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check tunerstop.products columns
$cols = $pdo->query("SHOW COLUMNS FROM tunerstop.products")->fetchAll(PDO::FETCH_COLUMN);
echo "tunerstop.products columns: " . implode(', ', $cols) . "\n\n";

$cols2 = $pdo->query("SHOW COLUMNS FROM reporting_crm.products")->fetchAll(PDO::FETCH_COLUMN);
echo "reporting_crm.products columns: " . implode(', ', $cols2) . "\n\n";

// Preview what will be inserted
$preview = $pdo->query("
    SELECT DISTINCT pv.product_id, tp.name, tp.product_full_name, tp.brand_id, tp.model_id, tp.finish_id
    FROM reporting_crm.product_variants pv
    LEFT JOIN reporting_crm.products p ON p.id = pv.product_id
    INNER JOIN tunerstop.products tp ON tp.id = pv.product_id
    WHERE p.id IS NULL
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
echo "Preview of products to insert:\n";
print_r($preview);
