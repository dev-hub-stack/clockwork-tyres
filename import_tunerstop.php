<?php
/**
 * Tunerstop Data Import Script
 * Direct DB-to-DB: tunerstop (local) → reporting_crm (local)
 * Run: php import_tunerstop.php
 */

$src = new PDO('mysql:host=127.0.0.1;port=3306;dbname=tunerstop;charset=utf8mb4', 'root', '');
$dst = new PDO('mysql:host=127.0.0.1;port=3306;dbname=reporting_crm;charset=utf8mb4', 'root', '');
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function log_msg(string $msg): void {
    echo "[" . date('H:i:s') . "] $msg\n";
    flush();
}

// ── Disable FK checks ────────────────────────────────────────────────────────
$dst->exec("SET FOREIGN_KEY_CHECKS = 0");
log_msg("FK checks disabled");

// ── Truncate destination tables ──────────────────────────────────────────────
foreach (['product_inventories', 'product_variants', 'products', 'finishes', 'models', 'brands'] as $tbl) {
    $dst->exec("TRUNCATE TABLE `$tbl`");
    log_msg("Truncated $tbl");
}

// ── 1. brands ────────────────────────────────────────────────────────────────
// tunerstop: id, name, image, description, uae, kuwait, bahrain, oman, ksa, created_at, updated_at, seo_title, meta_description, meta_keywords
// crm:       id, name, slug, logo, description, external_id, external_source, status, created_at, updated_at, deleted_at
$brands = $src->query("SELECT * FROM brands")->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare("
    INSERT INTO brands (id, name, logo, description, status, created_at, updated_at)
    VALUES (:id, :name, :logo, :description, 1, :created_at, :updated_at)
");
foreach ($brands as $b) {
    $stmt->execute([
        ':id'          => $b['id'],
        ':name'        => $b['name'],
        ':logo'        => $b['image'] ?? null,
        ':description' => $b['description'] ?? null,
        ':created_at'  => $b['created_at'],
        ':updated_at'  => $b['updated_at'],
    ]);
}
log_msg("Imported " . count($brands) . " brands");

// ── 2. models (identical schema) ─────────────────────────────────────────────
$dst->exec("
    INSERT INTO reporting_crm.models (id, name, image, created_at, updated_at)
    SELECT id, name, image, created_at, updated_at FROM tunerstop.models
");
log_msg("Imported " . $dst->query("SELECT COUNT(*) FROM models")->fetchColumn() . " models");

// ── 3. finishes (identical schema) ───────────────────────────────────────────
$dst->exec("
    INSERT INTO reporting_crm.finishes (id, finish, created_at, updated_at)
    SELECT id, finish, created_at, updated_at FROM tunerstop.finishes
");
log_msg("Imported " . $dst->query("SELECT COUNT(*) FROM finishes")->fetchColumn() . " finishes");

// ── 4. products ──────────────────────────────────────────────────────────────
// tunerstop: id, name, product_full_name, slug, price, model_id, finish_id, brand_id, images, construction, status, created_at, updated_at, ..., deleted_at
// crm:       id, external_product_id, external_source, name, sku, price, brand_id, model_id, finish_id, images, construction, status, available_on_wholesale, track_inventory, total_quantity, created_at, updated_at
$dst->exec("
    INSERT INTO reporting_crm.products
        (id, external_product_id, external_source, name, price, brand_id, model_id, finish_id,
         images, construction, status, available_on_wholesale, track_inventory, total_quantity,
         created_at, updated_at)
    SELECT
        id, id, 'tunerstop', name, price, brand_id, model_id, finish_id,
        images, construction, status, 1, 1, 0,
        created_at, updated_at
    FROM tunerstop.products
    WHERE deleted_at IS NULL
");
log_msg("Imported " . $dst->query("SELECT COUNT(*) FROM products")->fetchColumn() . " products");

// ── 5. product_variants ───────────────────────────────────────────────────────
// tunerstop: id, sku, size, bolt_pattern, hub_bore, offset, weight, backspacing, lipsize, finish,
//            max_wheel_load, rim_diameter, rim_width, cost, price, us_retail_price, uae_retail_price,
//            sale_price, clearance_corner, image, product_id, created_at, updated_at, deleted_at, supplier_stock
// crm:       id, external_variant_id, external_source, sku, finish_id, size, bolt_pattern, hub_bore,
//            offset, weight, backspacing, lipsize, finish, max_wheel_load, rim_diameter, rim_width,
//            cost, price, us_retail_price, uae_retail_price, sale_price, clearance_corner, image,
//            supplier_stock, product_id, created_at, updated_at
$dst->exec("
    INSERT INTO reporting_crm.product_variants
        (id, external_variant_id, external_source, sku, finish_id,
         size, bolt_pattern, hub_bore, offset, weight, backspacing, lipsize, finish,
         max_wheel_load, rim_diameter, rim_width, cost, price,
         us_retail_price, uae_retail_price, sale_price, clearance_corner,
         image, supplier_stock, product_id, created_at, updated_at)
    SELECT
        id, id, 'tunerstop', sku, NULL,
        size, bolt_pattern, hub_bore, offset, weight, backspacing, lipsize, finish,
        max_wheel_load, rim_diameter, rim_width, cost, price,
        us_retail_price, uae_retail_price, sale_price, clearance_corner,
        image, supplier_stock, product_id, created_at, updated_at
    FROM tunerstop.product_variants
    WHERE deleted_at IS NULL
");
log_msg("Imported " . $dst->query("SELECT COUNT(*) FROM product_variants")->fetchColumn() . " product_variants");

// ── Re-enable FK checks ──────────────────────────────────────────────────────
$dst->exec("SET FOREIGN_KEY_CHECKS = 1");
log_msg("FK checks re-enabled");
log_msg("DONE.");
