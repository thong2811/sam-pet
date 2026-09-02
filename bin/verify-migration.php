#!/usr/bin/env php
<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';
use Application\Database\Database;

$db = new Database(BASE_PATH . '/data/app.db', BASE_PATH . '/data/migrations');

echo "=== Verify Migration ===\n\n";

// Row counts
$tables = [
    'products', 'import_stock', 'export_stock', 'vet_care', 'expenses',
    'reports', 'export_invoices', 'owners_pets', 'medical_records',
    'stocktaking', 'repackage_history',
];
foreach ($tables as $t) {
    $c = $db->fetchScalar("SELECT COUNT(*) FROM $t");
    printf("  %-30s %d rows\n", $t, (int)$c);
}

// Sample data
echo "\nSample product:\n";
$p = $db->fetchOne('SELECT id, name, initStock, repackageStock, createdAt FROM products LIMIT 1');
echo '  ' . json_encode($p, JSON_UNESCAPED_UNICODE) . "\n";

echo "\nSample repackage_history:\n";
$rh = $db->fetchOne('SELECT id, date, fromProductName, toProductName, fromQuantity, toQuantity FROM repackage_history LIMIT 1');
echo '  ' . json_encode($rh, JSON_UNESCAPED_UNICODE) . "\n";

echo "\nNull timestamps:\n";
$nullProd = $db->fetchScalar('SELECT COUNT(*) FROM products WHERE createdAt IS NULL');
$nullVet  = $db->fetchScalar('SELECT COUNT(*) FROM vet_care WHERE createdAt IS NULL');
$nullExp  = $db->fetchScalar('SELECT COUNT(*) FROM expenses WHERE createdAt IS NULL');
printf("  products null createdAt:  %d\n", (int)$nullProd);
printf("  vet_care null createdAt:  %d\n", (int)$nullVet);
printf("  expenses null createdAt:  %d\n", (int)$nullExp);

echo "\nFK integrity:\n";
$o1 = $db->fetchScalar("SELECT COUNT(*) FROM import_stock WHERE productId != '' AND productId NOT IN (SELECT id FROM products)");
$o2 = $db->fetchScalar("SELECT COUNT(*) FROM export_stock WHERE productId != '' AND productId NOT IN (SELECT id FROM products)");
$o3 = $db->fetchScalar("SELECT COUNT(*) FROM stocktaking WHERE id NOT IN (SELECT id FROM products)");
printf("  import_stock orphans:     %d\n", (int)$o1);
printf("  export_stock orphans:     %d\n", (int)$o2);
printf("  stocktaking orphans:      %d\n", (int)$o3);

echo "\nrepackage_history rows per original CSV row (should be >= 1):\n";
$sample = $db->fetchAll("SELECT date, fromProductName, toProductName, fromQuantity, toQuantity FROM repackage_history WHERE date = '23-03-2025'");
foreach ($sample as $r) {
    echo '  ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\nDB size: " . round(filesize(BASE_PATH . '/data/app.db') / 1024, 1) . " KB\n";
echo "\n✅ Verification done\n";
