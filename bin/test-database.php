#!/usr/bin/env php
<?php

/**
 * Script kiểm tra Database layer:
 *  1. Tạo DB mới (hoặc mở DB hiện có)
 *  2. Chạy migration
 *  3. Verify tất cả 15 bảng và indexes tồn tại
 *  4. Kiểm tra PRAGMA user_version
 *  5. Test transactional helper
 *
 * Chạy: php bin/test-database.php
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use Application\Database\Database;

$testDbPath     = BASE_PATH . '/data/test_schema.db';
$migrationsDir  = BASE_PATH . '/data/migrations';

// Xóa DB test cũ nếu có để chạy lại từ đầu
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

echo "=== Test Database Layer ===\n\n";

// ----------------------------------------------------------------
// 1. Tạo DB + migrate
// ----------------------------------------------------------------
echo "1. Tạo DB và chạy migration...\n";
$db = new Database($testDbPath, $migrationsDir);
echo "   ✓ Database::__construct() OK\n";

// ----------------------------------------------------------------
// 2. Kiểm tra user_version
// ----------------------------------------------------------------
$version = $db->getUserVersion();
echo "2. Schema version: $version\n";
assert($version === 1, "user_version phải là 1 sau migration 001");
echo "   ✓ user_version = 1 (đúng)\n";

// ----------------------------------------------------------------
// 3. Verify 15 bảng tồn tại
// ----------------------------------------------------------------
echo "3. Kiểm tra 15 bảng...\n";
$expectedTables = [
    'categories',
    'products',
    'import_stock',
    'export_stock',
    'customers',
    'vet_care',
    'expenses',
    'reports',
    'export_invoices',
    'owners_pets',
    'medical_records',
    'stocktaking',
    'stocktaking_periods',
    'stocktaking_period_items',
    'repackage_history',
];

$existingTables = $db->fetchAll(
    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
);
$existingNames = array_column($existingTables, 'name');
$missing = array_diff($expectedTables, $existingNames);

if (!empty($missing)) {
    echo "   ✗ Thiếu bảng: " . implode(', ', $missing) . "\n";
    exit(1);
}
echo "   ✓ Tất cả " . count($expectedTables) . " bảng tồn tại\n";

// ----------------------------------------------------------------
// 4. Verify một số indexes quan trọng
// ----------------------------------------------------------------
echo "4. Kiểm tra indexes...\n";
$expectedIndexes = [
    'idx_products_categoryId',
    'idx_import_stock_date',
    'idx_import_stock_productId',
    'idx_export_stock_date',
    'idx_export_stock_productId',
    'idx_vet_care_date',
    'idx_expenses_date',
    'idx_reports_date',
    'uidx_reports_date',
    'idx_medical_records_pet_id',
    'idx_repackage_history_date',
    'idx_sp_items_periodId',
];

$existingIndexes = $db->fetchAll(
    "SELECT name FROM sqlite_master WHERE type='index' ORDER BY name"
);
$existingIndexNames = array_column($existingIndexes, 'name');
$missingIdx = array_diff($expectedIndexes, $existingIndexNames);

if (!empty($missingIdx)) {
    echo "   ✗ Thiếu index: " . implode(', ', $missingIdx) . "\n";
    exit(1);
}
echo "   ✓ Tất cả " . count($expectedIndexes) . " indexes tồn tại\n";

// ----------------------------------------------------------------
// 5. Test PRAGMA
// ----------------------------------------------------------------
echo "5. Kiểm tra PRAGMAs...\n";
$walMode = $db->fetchOne("PRAGMA journal_mode");
assert(($walMode['journal_mode'] ?? '') === 'wal', "journal_mode phải là WAL");
echo "   ✓ WAL mode ON\n";

$fkCheck = $db->fetchOne("PRAGMA foreign_keys");
assert((int)($fkCheck['foreign_keys'] ?? 0) === 1, "foreign_keys phải ON");
echo "   ✓ Foreign keys ON\n";

// ----------------------------------------------------------------
// 6. Test transactional()
// ----------------------------------------------------------------
echo "6. Test transaction helper...\n";
$db->transactional(function () use ($db) {
    $id = uniqid();
    $db->execute(
        "INSERT INTO categories (id, name, note, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)",
        [$id, 'Test Category', 'note', time(), time()]
    );
    $row = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$id]);
    assert($row['name'] === 'Test Category', "Tên category phải khớp");
});
echo "   ✓ transactional() commit OK\n";

// Test rollback
$rolledBack = false;
try {
    $db->transactional(function () use ($db) {
        $db->execute(
            "INSERT INTO categories (id, name) VALUES (?, ?)",
            [uniqid(), 'Will Rollback']
        );
        throw new \RuntimeException("Rollback test");
    });
} catch (\RuntimeException $e) {
    $rolledBack = true;
}
assert($rolledBack, "Exception phải được throw sau rollback");
$count = $db->fetchScalar("SELECT COUNT(*) FROM categories WHERE name = 'Will Rollback'");
assert((int)$count === 0, "Row phải bị rollback");
echo "   ✓ transactional() rollback OK\n";

// ----------------------------------------------------------------
// 7. Test FK: products.categoryId → categories.id
// ----------------------------------------------------------------
echo "7. Test FK constraint...\n";
$fkError = false;
try {
    $db->execute(
        "INSERT INTO products (id, name, categoryId) VALUES (?, ?, ?)",
        [uniqid(), 'Test Product', 'non-existent-category-id']
    );
} catch (\PDOException $e) {
    $fkError = true;
}
assert($fkError, "FK violation phải bị từ chối");
echo "   ✓ FK constraint hoạt động đúng\n";

// ----------------------------------------------------------------
// 8. Test vacuumInto()
// ----------------------------------------------------------------
echo "8. Test vacuumInto()...\n";
$backupPath = BASE_PATH . '/data/test_backup.db';
if (file_exists($backupPath)) {
    unlink($backupPath);
}
$db->vacuumInto($backupPath);
assert(file_exists($backupPath), "File backup phải tồn tại sau vacuumInto");
$sizeOriginal = $db->getDbSize();
$sizeBackup   = filesize($backupPath);
assert($sizeBackup > 0, "File backup không được rỗng");
echo "   ✓ vacuumInto() OK (original: {$sizeOriginal}B, backup: {$sizeBackup}B)\n";

// ----------------------------------------------------------------
// 9. Test idempotency: chạy migrate() lần 2 không thay đổi gì
// ----------------------------------------------------------------
echo "9. Test migration idempotency...\n";
$db->migrate(); // Gọi lại — không được throw exception hay tạo bảng trùng
$versionAfter = $db->getUserVersion();
assert($versionAfter === 1, "user_version không được thay đổi sau migrate() lần 2");
echo "   ✓ Migration idempotent OK\n";

// ----------------------------------------------------------------
// Dọn dẹp
// ----------------------------------------------------------------
unlink($testDbPath);
if (file_exists($backupPath)) {
    unlink($backupPath);
}

echo "\n✅ Tất cả test PASSED — Database layer sẵn sàng\n";
