#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Migrate CSV → SQLite
 * =====================
 * Đọc 11 file CSV trong ./data/ và insert vào ./data/app.db
 * theo đúng thứ tự FK dependency.
 *
 * Chiến lược:
 *  - Preserve toàn bộ id, createdAt, updatedAt gốc từ CSV
 *  - createdAt/updatedAt null trong CSV → null trong SQLite (không ép về 0)
 *  - INSERT OR IGNORE: row trùng id bị bỏ qua, ghi log cảnh báo
 *  - Tất cả bảng insert trong 1 transaction lớn; rollback nếu có lỗi nghiêm trọng
 *  - repackage_history: 1 CSV row → N SQLite rows (parse content text)
 *
 * Chạy: docker compose exec app php /var/www/html/bin/migrate-csv-to-sqlite.php
 * Hoặc:  php bin/migrate-csv-to-sqlite.php  (khi đứng tại gốc project)
 *
 * Options:
 *   --dry-run   Chỉ đọc và báo cáo, không ghi vào DB
 *   --force     Xóa DB hiện có và tạo lại từ đầu (CẢNH BÁO: mất dữ liệu)
 */

define('BASE_PATH', dirname(__DIR__));

// ── Bootstrap ────────────────────────────────────────────────────────────────

require BASE_PATH . '/vendor/autoload.php';

use Application\Database\Database;

$isDryRun = in_array('--dry-run', $argv ?? [], true);
$isForce  = in_array('--force',   $argv ?? [], true);

$dbPath        = BASE_PATH . '/data/app.db';
$migrationsDir = BASE_PATH . '/data/migrations';
$dataDir       = BASE_PATH . '/data';

echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║          Sam Pet — CSV → SQLite Migration                ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

if ($isDryRun) {
    echo "⚠️  DRY-RUN mode — không ghi vào database\n\n";
}

if ($isForce && !$isDryRun) {
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "🗑  Đã xóa database cũ: $dbPath\n\n";
    }
}

// ── Khởi tạo DB (tự migrate schema nếu chưa có) ──────────────────────────────

$db = new Database($dbPath, $migrationsDir);
echo "✓ Database sẵn sàng (schema v" . $db->getUserVersion() . "): $dbPath\n\n";

// ── Stats ─────────────────────────────────────────────────────────────────────

/** @var array<string, array{inserted: int, skipped: int, warnings: string[]}> */
$stats = [];

function initStats(string $table): void
{
    global $stats;
    $stats[$table] = ['inserted' => 0, 'skipped' => 0, 'warnings' => []];
}

function recordInserted(string $table): void
{
    global $stats;
    $stats[$table]['inserted']++;
}

function recordSkipped(string $table, string $reason): void
{
    global $stats;
    $stats[$table]['skipped']++;
    $stats[$table]['warnings'][] = $reason;
}

// ── CSV Reader ────────────────────────────────────────────────────────────────

/**
 * Đọc CSV bằng League\Csv, trả về iterator các associative rows.
 * Hỗ trợ multiline fields (content có newline), quoted fields.
 */
function readCsv(string $filePath): \League\Csv\TabularDataReader
{
    $reader = \League\Csv\Reader::createFromPath($filePath, 'r');
    $reader->setHeaderOffset(0);
    return $reader;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Chuẩn hoá timestamp:
 *  - null / '' / '0' → null (giữ nguyên nullability)
 *  - numeric string → integer
 */
function normalizeTs(?string $value): ?int
{
    if ($value === null || $value === '' || $value === '0') {
        return null;
    }
    $v = (int) $value;
    return $v > 0 ? $v : null;
}

/**
 * Chuẩn hoá REAL: '' → 0.0, numeric string → float
 */
function normalizeReal(?string $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return (float) $value;
}

/**
 * Chuẩn hoá TEXT: null → ''
 */
function normalizeText(?string $value): string
{
    return $value ?? '';
}

// ── Repackage content parser ──────────────────────────────────────────────────

/**
 * Parse content text của repackage_history CSV thành danh sách các cặp
 * (fromName, fromQty) → [(toName, toQty), ...]
 *
 * Format content:
 *   Nhập chiết hàng cho ngày DD-MM-YYYY.
 *   Chi tiết:
 *       -qty ProductName (Tồn hiện tại: X, ...)
 *       +qty ProductName (Tồn hiện tại: X, ...)
 *       +qty ProductName (Tồn hiện tại: X, ...)
 *
 * Returns: [
 *   ['fromName' => '...', 'fromQty' => 1.0, 'toName' => '...', 'toQty' => 10.0],
 *   ...  (1 row per toProduct)
 * ]
 */
function parseRepackageContent(string $content): array
{
    $lines = explode("\n", $content);
    $fromName = '';
    $fromQty  = 0.0;
    $toItems  = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Dòng nguồn: bắt đầu bằng "-" hoặc "\t-"
        // Pattern: -qty ProductName (...)
        if (preg_match('/^-(\d+(?:\.\d+)?)\s+(.+?)\s*\(/', $line, $m)) {
            $fromQty  = (float) $m[1];
            // Lấy tên sản phẩm, bỏ phần trong ngoặc
            $fromName = trim($m[2]);
            continue;
        }

        // Dòng đích: bắt đầu bằng "+" hoặc "\t+"
        // Pattern: +qty ProductName (...)
        if (preg_match('/^\+(\d+(?:\.\d+)?)\s+(.+?)\s*\(/', $line, $m)) {
            $toItems[] = [
                'toName' => trim($m[2]),
                'toQty'  => (float) $m[1],
            ];
            continue;
        }
    }

    // Không parse được gì — có thể format khác
    if ($fromName === '' && empty($toItems)) {
        return [];
    }

    // Tạo 1 row per toProduct, tất cả share cùng fromName/fromQty
    $result = [];
    foreach ($toItems as $to) {
        $result[] = [
            'fromName' => $fromName,
            'fromQty'  => $fromQty,
            'toName'   => $to['toName'],
            'toQty'    => $to['toQty'],
        ];
    }

    // Nếu chỉ có from mà không có to (edge case)
    if (empty($result) && $fromName !== '') {
        $result[] = [
            'fromName' => $fromName,
            'fromQty'  => $fromQty,
            'toName'   => '',
            'toQty'    => 0.0,
        ];
    }

    return $result;
}

// ── Migration functions ───────────────────────────────────────────────────────

function migrateProducts(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'products';
    $filePath = $dataDir . '/product.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP (file không tồn tại)\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO products
                (id, name, unit, sellingPrice, purchasePrice, initStock, repackageStock, invoiceCheck, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "Row bỏ qua: id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['name'] ?? ''),
                normalizeText($row['unit'] ?? ''),
                normalizeReal($row['sellingPrice'] ?? ''),
                normalizeReal($row['purchasePrice'] ?? ''),
                normalizeReal($row['initStock'] ?? ''),
                normalizeReal($row['repackageStock'] ?? ''),
                normalizeText($row['invoiceCheck'] ?? '0') ?: '0',
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            if ($stmt->rowCount() > 0) {
                recordInserted($table);
            } else {
                recordSkipped($table, "Duplicate id: $id");
            }
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateImportStock(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'import_stock';
    $filePath = $dataDir . '/import-stock.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO import_stock
                (id, date, productId, productName, quantity, purchasePrice, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                normalizeText($row['productId'] ?? ''),
                normalizeText($row['productName'] ?? ''),
                normalizeReal($row['quantity'] ?? ''),
                normalizeReal($row['purchasePrice'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateExportStock(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'export_stock';
    $filePath = $dataDir . '/export-stock.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO export_stock
                (id, date, productId, productName, quantity, sellingPrice, purchasePrice, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                normalizeText($row['productId'] ?? ''),
                normalizeText($row['productName'] ?? ''),
                normalizeReal($row['quantity'] ?? ''),
                normalizeReal($row['sellingPrice'] ?? ''),
                normalizeReal($row['purchasePrice'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateVetCare(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'vet_care';
    $filePath = $dataDir . '/vet-care.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO vet_care
                (id, date, treatmentAmount, spaAmount, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                normalizeReal($row['treatmentAmount'] ?? ''),
                normalizeReal($row['spaAmount'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateExpenses(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'expenses';
    $filePath = $dataDir . '/expenses.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO expenses
                (id, date, type, reason, amount, person, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                normalizeText($row['type'] ?? '0') ?: '0',
                normalizeText($row['reason'] ?? ''),
                normalizeReal($row['amount'] ?? ''),
                normalizeText($row['person'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateReports(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'reports';
    $filePath = $dataDir . '/report.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO reports
                (id, date, petShopRevenue, petShopProfit, spaRevenue, treatmentRevenue,
                 expenses, savings, missingAmount, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                normalizeReal($row['petShopRevenue'] ?? ''),
                normalizeReal($row['petShopProfit'] ?? ''),
                normalizeReal($row['spaRevenue'] ?? ''),
                normalizeReal($row['treatmentRevenue'] ?? ''),
                normalizeReal($row['expenses'] ?? ''),
                normalizeReal($row['savings'] ?? ''),
                normalizeReal($row['missingAmount'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateExportInvoices(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'export_invoices';
    $filePath = $dataDir . '/export-invoice.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO export_invoices
                (id, date, content, total, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        // Validate JSON content
        $content = normalizeText($row['content'] ?? '');
        if ($content !== '' && json_decode($content) === null) {
            recordSkipped($table, "JSON không hợp lệ cho id=$id, bỏ qua row");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['date'] ?? ''),
                $content ?: '{}',
                normalizeReal($row['total'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateOwnersPets(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'owners_pets';
    $filePath = $dataDir . '/owners_pets.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO owners_pets
                (id, owner_name, phone, pet_name, species, breed, gender, age, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['owner_name'] ?? ''),
                normalizeText($row['phone'] ?? ''),
                normalizeText($row['pet_name'] ?? ''),
                normalizeText($row['species'] ?? ''),
                normalizeText($row['breed'] ?? ''),
                normalizeText($row['gender'] ?? ''),
                normalizeText($row['age'] ?? ''),
                normalizeText($row['note'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateMedicalRecords(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'medical_records';
    $filePath = $dataDir . '/medical_records.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    $sql = "INSERT OR IGNORE INTO medical_records
                (id, pet_id, visit_date, symptoms, diagnosis, prescription, start_date, end_date, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                normalizeText($row['pet_id'] ?? ''),
                normalizeText($row['visit_date'] ?? ''),
                normalizeText($row['symptoms'] ?? ''),
                normalizeText($row['diagnosis'] ?? ''),
                normalizeText($row['prescription'] ?? ''),
                normalizeText($row['start_date'] ?? ''),
                normalizeText($row['end_date'] ?? ''),
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

function migrateStocktaking(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'stocktaking';
    $filePath = $dataDir . '/stocktaking.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    // stocktaking.id = productId — dùng INSERT OR REPLACE để upsert
    $sql = "INSERT OR IGNORE INTO stocktaking
                (id, stocktaking, createdAt, updatedAt)
            VALUES (?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $id = normalizeText($row['id'] ?? '');
        if ($id === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        // stocktaking value: '' → null (belum di-isi), numeric → float
        $stocktakingVal = $row['stocktaking'] ?? '';
        $stocktakingNorm = ($stocktakingVal === '' || $stocktakingVal === null)
            ? null
            : (float) $stocktakingVal;

        if (!$dryRun) {
            $stmt = $db->execute($sql, [
                $id,
                $stocktakingNorm,
                normalizeTs($row['createdAt'] ?? ''),
                normalizeTs($row['updatedAt'] ?? ''),
            ]);
            $stmt->rowCount() > 0 ? recordInserted($table) : recordSkipped($table, "Duplicate: $id");
        } else {
            recordInserted($table);
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

/**
 * repackage_history: Bảng phức tạp nhất.
 *
 * Mỗi CSV row có thể có 1 nguồn và N đích.
 * Ta tạo N rows SQLite — mỗi row là 1 cặp (from → to).
 *
 * Vì content text không chứa productId (chỉ có tên),
 * ta để fromProductId = '' và toProductId = '' (không có FK thật),
 * lưu tên vào fromProductName / toProductName.
 * note = content gốc để không mất thông tin.
 *
 * ID của row SQLite = originalId + '_' + index (để tránh trùng).
 * Row gốc (index 0) giữ nguyên originalId để backward compat nếu có reference.
 */
function migrateRepackageHistory(Database $db, string $dataDir, bool $dryRun): void
{
    $table    = 'repackage_history';
    $filePath = $dataDir . '/repackage_history.csv';
    initStats($table);
    echo "→ Migrating $table ... ";

    if (!file_exists($filePath)) {
        echo "SKIP\n";
        return;
    }

    // fromProductId / toProductId: NULL vì CSV không có ID, chỉ có tên
    // FK constraint: NOT NULL không đặt trên cột này trong schema → NULL hợp lệ
    $sql = "INSERT OR IGNORE INTO repackage_history
                (id, date, fromProductId, fromProductName, toProductId, toProductName,
                 fromQuantity, toQuantity, note, createdAt, updatedAt)
            VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, ?, ?)";

    foreach (readCsv($filePath) as $row) {
        $originalId = normalizeText($row['id'] ?? '');
        if ($originalId === '') {
            recordSkipped($table, "id rỗng");
            continue;
        }

        $date      = normalizeText($row['date'] ?? '');
        $content   = normalizeText($row['content'] ?? '');
        $createdAt = normalizeTs($row['createdAt'] ?? '');
        $updatedAt = normalizeTs($row['updatedAt'] ?? '');

        // Parse content → [(fromName, fromQty, toName, toQty), ...]
        $parsed = parseRepackageContent($content);

        if (empty($parsed)) {
            // Không parse được — lưu 1 row với note = content gốc, fields rỗng
            if (!$dryRun) {
                $stmt = $db->execute($sql, [
                    $originalId, $date,
                    '', // fromProductName
                    '', // toProductName
                    0.0, 0.0,
                    $content,   // giữ nguyên content gốc trong note
                    $createdAt, $updatedAt,
                ]);
                $stmt->rowCount() > 0
                    ? recordInserted($table)
                    : recordSkipped($table, "Duplicate: $originalId");
            } else {
                recordInserted($table);
            }
            recordSkipped($table, "⚠ Không parse được content: id=$originalId");
            continue;
        }

        foreach ($parsed as $index => $pair) {
            // Row đầu tiên giữ nguyên originalId; các row sau append _1, _2, ...
            $rowId = $index === 0 ? $originalId : "{$originalId}_{$index}";

            if (!$dryRun) {
                $stmt = $db->execute($sql, [
                    $rowId,
                    $date,
                    $pair['fromName'],
                    $pair['toName'],
                    $pair['fromQty'],
                    $pair['toQty'],
                    $index === 0 ? $content : '', // note chỉ lưu ở row đầu
                    $createdAt,
                    $updatedAt,
                ]);
                $stmt->rowCount() > 0
                    ? recordInserted($table)
                    : recordSkipped($table, "Duplicate: $rowId");
            } else {
                recordInserted($table);
            }
        }
    }

    $s = global_stats($table);
    echo "inserted={$s['inserted']} skipped={$s['skipped']}\n";
}

// Helper để lấy stats (tránh global variable access trong closures)
function global_stats(string $table): array
{
    global $stats;
    return $stats[$table] ?? ['inserted' => 0, 'skipped' => 0, 'warnings' => []];
}

// ── Chạy migration ────────────────────────────────────────────────────────────

echo "Bắt đầu migration...\n";
echo str_repeat('─', 60) . "\n";

$startTime = microtime(true);

if (!$isDryRun) {
    $db->beginTransaction();
}

$fatalError = null;

try {
    // Thứ tự theo FK dependency:
    // 1. products (không có FK)
    // 2. import_stock, export_stock (FK → products)
    // 3. vet_care, expenses, reports (không FK đến products)
    // 4. export_invoices (không FK nghiêm ngặt — content JSON)
    // 5. owners_pets (không FK)
    // 6. medical_records (FK → owners_pets)
    // 7. stocktaking (FK → products)
    // 8. repackage_history (FK → products, nhưng fromProductId = '' nên không vi phạm FK)

    migrateProducts($db, $dataDir, $isDryRun);
    migrateImportStock($db, $dataDir, $isDryRun);
    migrateExportStock($db, $dataDir, $isDryRun);
    migrateVetCare($db, $dataDir, $isDryRun);
    migrateExpenses($db, $dataDir, $isDryRun);
    migrateReports($db, $dataDir, $isDryRun);
    migrateExportInvoices($db, $dataDir, $isDryRun);
    migrateOwnersPets($db, $dataDir, $isDryRun);
    migrateMedicalRecords($db, $dataDir, $isDryRun);
    migrateStocktaking($db, $dataDir, $isDryRun);
    migrateRepackageHistory($db, $dataDir, $isDryRun);

    if (!$isDryRun) {
        $db->commit();
    }
} catch (\Throwable $e) {
    $fatalError = $e;
    if (!$isDryRun) {
        $db->rollBack();
    }
}

$elapsed = round(microtime(true) - $startTime, 2);

// ── Báo cáo kết quả ───────────────────────────────────────────────────────────

echo str_repeat('─', 60) . "\n\n";
echo "📊 Báo cáo Migration\n";
echo str_repeat('═', 60) . "\n";
printf("%-30s %10s %10s\n", 'Bảng', 'Inserted', 'Skipped');
echo str_repeat('─', 60) . "\n";

$totalInserted = 0;
$totalSkipped  = 0;

foreach ($stats as $table => $s) {
    printf("%-30s %10d %10d\n", $table, $s['inserted'], $s['skipped']);
    $totalInserted += $s['inserted'];
    $totalSkipped  += $s['skipped'];
}

echo str_repeat('─', 60) . "\n";
printf("%-30s %10d %10d\n", 'TỔNG', $totalInserted, $totalSkipped);
echo str_repeat('═', 60) . "\n\n";

// Warnings
$hasWarnings = false;
foreach ($stats as $table => $s) {
    if (!empty($s['warnings'])) {
        if (!$hasWarnings) {
            echo "⚠️  Warnings:\n";
            $hasWarnings = true;
        }
        foreach ($s['warnings'] as $w) {
            echo "   [$table] $w\n";
        }
    }
}
if ($hasWarnings) {
    echo "\n";
}

if ($fatalError) {
    echo "❌ FATAL ERROR: " . $fatalError->getMessage() . "\n";
    echo "   Toàn bộ transaction đã rollback — CSV gốc không bị ảnh hưởng.\n\n";
    exit(1);
}

// Verify row counts (chỉ khi không dry-run)
if (!$isDryRun) {
    echo "🔍 Xác minh row count trong DB:\n";
    $tables = [
        'products', 'import_stock', 'export_stock', 'vet_care', 'expenses',
        'reports', 'export_invoices', 'owners_pets', 'medical_records',
        'stocktaking', 'repackage_history',
    ];
    foreach ($tables as $t) {
        $count = $db->fetchScalar("SELECT COUNT(*) FROM $t");
        printf("   %-30s %d rows\n", $t, (int) $count);
    }
    echo "\n";

    // FK integrity check
    echo "🔗 Kiểm tra FK integrity:\n";

    $orphanImport = $db->fetchScalar(
        "SELECT COUNT(*) FROM import_stock WHERE productId != '' AND productId NOT IN (SELECT id FROM products)"
    );
    printf("   import_stock orphan productId:  %d\n", (int) $orphanImport);

    $orphanExport = $db->fetchScalar(
        "SELECT COUNT(*) FROM export_stock WHERE productId != '' AND productId NOT IN (SELECT id FROM products)"
    );
    printf("   export_stock orphan productId:  %d\n", (int) $orphanExport);

    $orphanMedical = $db->fetchScalar(
        "SELECT COUNT(*) FROM medical_records WHERE pet_id != '' AND pet_id NOT IN (SELECT id FROM owners_pets)"
    );
    printf("   medical_records orphan pet_id:  %d\n", (int) $orphanMedical);

    $orphanStocktaking = $db->fetchScalar(
        "SELECT COUNT(*) FROM stocktaking WHERE id NOT IN (SELECT id FROM products)"
    );
    printf("   stocktaking orphan productId:   %d\n", (int) $orphanStocktaking);

    echo "\n";

    $dbSize = round($db->getDbSize() / 1024, 1);
    echo "💾 Kích thước DB: {$dbSize} KB\n";
}

echo "⏱  Thời gian: {$elapsed}s\n\n";

if ($isDryRun) {
    echo "✅ Dry-run hoàn thành — không có gì được ghi vào DB.\n";
    echo "   Chạy lại không có --dry-run để thực hiện migration thật.\n";
} else {
    echo "✅ Migration hoàn thành thành công!\n";
}
echo "\n";
