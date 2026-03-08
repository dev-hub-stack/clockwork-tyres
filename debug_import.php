<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=reporting_crm', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Are the product_ids already in reporting_crm.products with a different id?
$r = $pdo->query("
    SELECT pv.product_id, p.id as existing_id, p.name
    FROM reporting_crm.product_variants pv
    LEFT JOIN reporting_crm.products p ON p.id = pv.product_id
    WHERE p.id IS NULL
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);
echo "Orphaned variants sample:\n"; print_r($r);

// Check if id=141 exists in reporting_crm.products
$r2 = $pdo->query("SELECT id, name FROM reporting_crm.products WHERE id IN (141,142,143)")->fetchAll(PDO::FETCH_ASSOC);
echo "\nCheck if ids 141,142,143 exist in reporting_crm.products:\n"; print_r($r2);

// What IS in reporting_crm.products - min/max id?
$r3 = $pdo->query("SELECT MIN(id), MAX(id), COUNT(*) FROM reporting_crm.products")->fetch(PDO::FETCH_NUM);
echo "\nreporting_crm.products: min_id={$r3[0]}, max_id={$r3[1]}, count={$r3[2]}\n";

// Try inserting one row manually
echo "\nTrying manual insert of id=141...\n";
try {
    $pdo->exec("INSERT INTO reporting_crm.products (id, name, status, created_at, updated_at) VALUES (141, 'TEST', 1, NOW(), NOW())");
    echo "Inserted OK\n";
    $pdo->exec("DELETE FROM reporting_crm.products WHERE id=141 AND name='TEST'");
    echo "Cleaned up test row\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
