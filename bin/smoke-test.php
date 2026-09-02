#!/usr/bin/env php
<?php
/**
 * Smoke test — kiểm tra app bootstrap và các trang chính không crash.
 * Chạy: docker exec sam-pet-dev php /var/www/html/bin/smoke-test.php
 */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

use Application\Database\Database;

$db = new Database(BASE_PATH . '/data/app.db', BASE_PATH . '/data/migrations');

echo "=== Smoke Test ===\n\n";
$pass = 0; $fail = 0;

function check(string $label, callable $fn): void {
    global $pass, $fail;
    try {
        $result = $fn();
        $ok = $result !== false && $result !== null;
        if ($ok) { echo "✓ $label\n"; $pass++; }
        else      { echo "✗ $label (returned false/null)\n"; $fail++; }
    } catch (\Throwable $e) {
        echo "✗ $label → " . $e->getMessage() . "\n";
        $fail++;
    }
}

// ── Database layer ────────────────────────────────────────────────────────────
check("DB schema v1", fn() => $db->getUserVersion() === 1);
check("DB WAL mode",  fn() => $db->fetchOne("PRAGMA journal_mode")['journal_mode'] === 'wal');
check("DB FK ON",     fn() => (int)$db->fetchOne("PRAGMA foreign_keys")['foreign_keys'] === 1);

// ── Repository instantiation ──────────────────────────────────────────────────
$repos = [
    'ProductRepository'          => \Application\Repository\ProductRepository::class,
    'ImportStockRepository'      => \Application\Repository\ImportStockRepository::class,
    'ExportStockRepository'      => \Application\Repository\ExportStockRepository::class,
    'VetCareRepository'          => \Application\Repository\VetCareRepository::class,
    'ExpensesRepository'         => \Application\Repository\ExpensesRepository::class,
    'ReportRepository'           => \Application\Repository\ReportRepository::class,
    'ExportInvoiceRepository'    => \Application\Repository\ExportInvoiceRepository::class,
    'OwnerPetRepository'         => \Application\Repository\OwnerPetRepository::class,
    'MedicalRecordRepository'    => \Application\Repository\MedicalRecordRepository::class,
    'StocktakingRepository'      => \Application\Repository\StocktakingRepository::class,
    'RepackageHistoryRepository' => \Application\Repository\RepackageHistoryRepository::class,
];
foreach ($repos as $name => $class) {
    check("new $name", function() use ($class, $db) { return new $class($db); });
}

// ── Core data reads ───────────────────────────────────────────────────────────
$productRepo  = new \Application\Repository\ProductRepository($db);
$importRepo   = new \Application\Repository\ImportStockRepository($db);
$exportRepo   = new \Application\Repository\ExportStockRepository($db);
$vetCareRepo  = new \Application\Repository\VetCareRepository($db);
$expensesRepo = new \Application\Repository\ExpensesRepository($db);
$reportRepo   = new \Application\Repository\ReportRepository($db);
$invoiceRepo  = new \Application\Repository\ExportInvoiceRepository($db);
$ownerRepo    = new \Application\Repository\OwnerPetRepository($db);
$medicalRepo  = new \Application\Repository\MedicalRecordRepository($db);
$stockRepo    = new \Application\Repository\StocktakingRepository($db);
$historyRepo  = new \Application\Repository\RepackageHistoryRepository($db);

check("ProductRepository::getDataToView (SQL JOIN)", function() use ($productRepo) {
    [$totals, $list] = $productRepo->getDataToView();
    assert(count($list) > 0, "Phải có sản phẩm");
    $first = reset($list);
    assert(array_key_exists('remainStock', $first), "remainStock phải có trong row");
    assert(array_key_exists('importStock', $first), "importStock phải có");
    return true;
});

check("ProductRepository::getData", fn() => count($productRepo->getData()) > 0);
check("ProductRepository::getProductNameList", fn() => count($productRepo->getProductNameList()) > 0);

check("ImportStockRepository::getDataToView", function() use ($importRepo) {
    $data = $importRepo->getDataToView();
    $first = reset($data);
    assert(array_key_exists('total', $first), "total phải có");
    return count($data) > 0;
});

check("ExportStockRepository::totalAmountByDate", function() use ($exportRepo) {
    $data = $exportRepo->totalAmountByDate();
    return is_array($data);
});

check("ExportStockRepository::totalQuantityByProduct", fn() => is_array($exportRepo->totalQuantityByProduct()));
check("ExportStockRepository::filterNewRows (empty → empty)", fn() => $exportRepo->filterNewRows([]) === []);

check("VetCareRepository::totalAmountByDate", function() use ($vetCareRepo) {
    $data = $vetCareRepo->totalAmountByDate();
    // Mỗi row phải có 3 keys
    if (!empty($data)) {
        $first = reset($data);
        assert(isset($first['treatment'], $first['spa'], $first['treatmentProfit']));
    }
    return is_array($data);
});

check("ExpensesRepository::totalAmountByDate", function() use ($expensesRepo) {
    [$exp, $sav] = $expensesRepo->totalAmountByDate();
    return is_array($exp) && is_array($sav);
});

check("ReportRepository::getDataToView", function() use ($reportRepo) {
    [$totals, $data] = $reportRepo->getDataToView();
    assert(array_key_exists('totalRevenue', $totals));
    if (!empty($data)) {
        $first = reset($data);
        assert(array_key_exists('revenue', $first));
        assert(array_key_exists('remaining', $first));
    }
    return true;
});

check("ReportRepository::getDataToViewChart", function() use ($reportRepo) {
    [$totals, $chart] = $reportRepo->getDataToViewChart();
    assert(array_key_exists('totalRevenue', $totals));
    return true;
});

check("ReportRepository::getDataByDate", function() use ($reportRepo) {
    $data = $reportRepo->getDataByDate('01-12-2024');
    assert(array_key_exists('petShopRevenue', $data));
    assert(array_key_exists('treatmentProfit', $data));
    return true;
});

check("ExportInvoiceRepository::getData", fn() => count($invoiceRepo->getData()) > 0);

check("OwnerPetRepository::searchByPetName", function() use ($ownerRepo) {
    $results = $ownerRepo->searchByPetName('Gold');
    return is_array($results);
});

check("MedicalRecordRepository::getDataToView (JOIN)", function() use ($medicalRepo) {
    $data = $medicalRepo->getDataToView();
    if (!empty($data)) {
        $first = reset($data);
        assert(array_key_exists('pet_name', $first), "pet_name phải có từ JOIN");
    }
    return is_array($data);
});

check("StocktakingRepository::getData", fn() => is_array($stockRepo->getData()));
check("RepackageHistoryRepository::getDataToView", fn() => is_array($historyRepo->getDataToView(10)));

// ── calcRemainStock trên 1 sản phẩm ──────────────────────────────────────────
check("ProductRepository::calcRemainStock", function() use ($productRepo) {
    $products = $productRepo->getData();
    if (empty($products)) return true;
    $id    = array_key_first($products);
    $stock = $productRepo->calcRemainStock($id);
    return is_float($stock) || is_int($stock);
});

// ── Database::vacuumInto ──────────────────────────────────────────────────────
check("Database::vacuumInto", function() use ($db) {
    $tmp = BASE_PATH . '/data/cache/smoke_test_backup.db';
    $db->vacuumInto($tmp);
    $ok = file_exists($tmp) && filesize($tmp) > 0;
    @unlink($tmp);
    return $ok;
});

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n";
echo "PASS: $pass  FAIL: $fail\n";
if ($fail === 0) {
    echo "\n✅ Tất cả smoke test PASSED — app sẵn sàng!\n\n";
    exit(0);
} else {
    echo "\n❌ $fail test FAILED\n\n";
    exit(1);
}
