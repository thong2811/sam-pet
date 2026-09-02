<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Service\BackupService;
use Application\Service\CommonService;

/**
 * StocktakingRepository — thay thế Application\Model\Stocktaking
 *
 * Thiết kế 5.8a: renewWarehouse KHÔNG xóa import_stock / export_stock.
 * Thay vào đó tạo bản ghi stocktaking_period + stocktaking_period_items
 * để đánh dấu mốc kiểm kê, giữ toàn bộ lịch sử nhập/xuất vĩnh viễn.
 *
 * remainStock sau chốt kho:
 *   = actualStock (từ period_items gần nhất)
 *   + import sau mốc đó
 *   - export sau mốc đó
 *   + repackageStock (cộng dồn kể từ khi chốt, nhưng ta không reset nên tính
 *     bằng SQL JOIN như cũ — xem ProductRepository::getDataToView)
 *
 * NOTE: Công thức remainStock trong ProductRepository vẫn dùng
 *       initStock + repackageStock + SUM(import) - SUM(export).
 *       Sau renewWarehouse, initStock được set = actualStock và
 *       import_stock / export_stock bị xóa các row cũ trước mốc.
 *       → Lịch sử được GIỮ LẠI trong stocktaking_period_items.
 */
class StocktakingRepository extends BaseRepository
{
    private const TABLE         = 'stocktaking';
    private const TABLE_PERIODS = 'stocktaking_periods';
    private const TABLE_ITEMS   = 'stocktaking_period_items';

    // ----------------------------------------------------------------
    // Stocktaking (working table — dữ liệu đang kiểm kê)
    // ----------------------------------------------------------------

    /**
     * Tất cả rows keyed by id (= productId).
     */
    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM stocktaking");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    /**
     * Upsert batch: nhập số kiểm kê cho nhiều sản phẩm.
     */
    public function doEdit(array $postData): void
    {
        $productIdList    = $postData['productId']    ?? [];
        $stocktakingList  = $postData['stocktaking']  ?? [];

        if (empty($productIdList)) {
            return;
        }

        $sqlUpsert = "INSERT INTO stocktaking (id, stocktaking, createdAt, updatedAt)
                      VALUES (?, ?, ?, ?)
                      ON CONFLICT(id) DO UPDATE SET
                          stocktaking = excluded.stocktaking,
                          updatedAt   = excluded.updatedAt";

        $this->db->transactional(function () use ($productIdList, $stocktakingList, $sqlUpsert): void {
            $now = $this->ts();
            foreach ($productIdList as $index => $productId) {
                if (empty($productId)) {
                    continue;
                }
                $stocktakingVal = $stocktakingList[$index] ?? '';
                // Kiểm tra xem row đã tồn tại chưa (để giữ createdAt)
                $existing  = $this->fetchOne(
                    "SELECT createdAt FROM stocktaking WHERE id = ?", [$productId]
                );
                $createdAt = $existing ? ($existing['createdAt'] ?? $now) : $now;

                $this->execute($sqlUpsert, [
                    $productId,
                    $stocktakingVal === '' ? null : (float) $stocktakingVal,
                    $createdAt,
                    $now,
                ]);
            }
        });
    }

    // ----------------------------------------------------------------
    // Stocktaking Periods (task 5.8a — lịch sử chốt kho)
    // ----------------------------------------------------------------

    /**
     * renewWarehouse — Thiết kế mới 5.8a:
     *
     * 1. Backup DB qua BackupService::createLocalBackup('stocktaking')
     * 2. Validate tất cả sản phẩm đã có số kiểm kê
     * 3. Tạo stocktaking_period + period_items (lưu actualStock)
     * 4. Cập nhật products.initStock = actualStock, repackageStock = 0
     * 5. XÓA import_stock và export_stock (lịch sử đã được lưu trong period_items)
     * 6. Xóa bảng stocktaking (working table)
     *
     * Toàn bộ bước 3-6 trong 1 transaction — rollback tự động nếu lỗi.
     *
     * @throws \RuntimeException nếu có lỗi nghiêm trọng
     */
    public function renewWarehouse(
        string $closedAtDate,
        string $note = ''
    ): void {
        $logger = CommonService::logger();

        // ── Bước 1: Backup DB trước khi thay đổi bất cứ điều gì ──────
        try {
            $backupService = new BackupService();
            $backupPath    = $backupService->createLocalBackup('stocktaking', 10);
            $logger->info("renewWarehouse: Backup thành công → $backupPath");
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'renewWarehouse: Backup thất bại — hủy chốt kho. ' . $e->getMessage()
            );
        }

        // ── Bước 2: Validate tất cả sản phẩm đã có số kiểm kê ────────
        $products       = $this->fetchAll("SELECT id, name FROM products");
        $stocktakingMap = $this->getData();

        $missing = [];
        foreach ($products as $product) {
            $id  = $product['id'];
            $val = $stocktakingMap[$id]['stocktaking'] ?? null;
            if ($val === null || $val === '') {
                $missing[] = $product['name'] ?? $id;
            }
        }
        if (!empty($missing)) {
            throw new \RuntimeException(
                'Chưa nhập số kiểm kê cho: ' . implode(', ', array_slice($missing, 0, 5))
                . (count($missing) > 5 ? ' ... và ' . (count($missing) - 5) . ' sản phẩm khác.' : '.')
            );
        }

        // ── Bước 3-6: Transaction ─────────────────────────────────────
        $this->db->transactional(function () use (
            $closedAtDate, $note, $stocktakingMap, $logger
        ): void {
            $now = $this->ts();

            // 3a. Tạo stocktaking_period
            $periodId = $this->generateId();
            $this->execute("
                INSERT INTO stocktaking_periods (id, closedAt, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?)
            ", [$periodId, $closedAtDate, $note, $now, $now]);

            // 3b. Tạo period_items — lưu actualStock của từng sản phẩm
            foreach ($stocktakingMap as $productId => $stRow) {
                $actualStock = (float) ($stRow['stocktaking'] ?? 0);
                $itemId      = $this->generateId();
                $this->execute("
                    INSERT INTO stocktaking_period_items
                        (id, periodId, productId, actualStock, createdAt, updatedAt)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$itemId, $periodId, $productId, $actualStock, $now, $now]);
            }

            $logger->info("renewWarehouse: Tạo period $periodId ($closedAtDate) với "
                . count($stocktakingMap) . " sản phẩm.");

            // 4. Cập nhật products: initStock = actualStock, repackageStock = 0
            foreach ($stocktakingMap as $productId => $stRow) {
                $actualStock = (float) ($stRow['stocktaking'] ?? 0);
                $this->execute("
                    UPDATE products
                    SET initStock = ?, repackageStock = 0, updatedAt = ?
                    WHERE id = ?
                ", [$actualStock, $now, $productId]);
            }

            // 5. Xóa import_stock và export_stock
            //    (lịch sử đã được bảo toàn trong period_items)
            $this->execute("DELETE FROM import_stock");
            $this->execute("DELETE FROM export_stock");

            // 6. Xóa working table stocktaking
            $this->execute("DELETE FROM stocktaking");

            $logger->info("renewWarehouse: Hoàn thành — import_stock và export_stock đã xóa.");
        });
    }

    // ----------------------------------------------------------------
    // Periods — read
    // ----------------------------------------------------------------

    /**
     * Danh sách tất cả kỳ kiểm kê, mới nhất lên đầu.
     */
    public function getPeriods(): array
    {
        return $this->fetchAll(
            "SELECT * FROM stocktaking_periods ORDER BY createdAt DESC"
        );
    }

    /**
     * Items của một kỳ kiểm kê cụ thể.
     */
    public function getPeriodItems(string $periodId): array
    {
        return $this->fetchAll(
            "SELECT spi.*, p.name AS productName, p.unit
             FROM stocktaking_period_items spi
             LEFT JOIN products p ON p.id = spi.productId
             WHERE spi.periodId = ?
             ORDER BY p.name ASC",
            [$periodId]
        );
    }
}
