<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * RepackageHistoryRepository — thay thế Application\Model\RepackageHistory
 * Schema mới (task 5.15): fromProductId, fromProductName, toProductId, toProductName,
 *                          fromQuantity, toQuantity, note
 */
class RepackageHistoryRepository extends BaseRepository
{
    private const TABLE = 'repackage_history';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /**
     * Danh sách lịch sử chiết, sắp xếp mới nhất lên đầu.
     * Group các rows cùng phiên chiết (cùng fromProductName + date) lại thành 1 entry
     * để hiển thị đúng với schema mới (1 phiên → N rows).
     *
     * @param int|null $limit  Giới hạn số phiên chiết (null = tất cả)
     * @return array  [groupKey => ['date', 'createdAt', 'fromProductName', 'fromQuantity', 'note', 'toItems' => [...]]]
     */
    public function getDataToView(?int $limit = null): array
    {
        $sql = "SELECT * FROM repackage_history ORDER BY createdAt DESC, id ASC";
        $rows = $this->fetchAll($sql);

        // Group theo phiên chiết: cùng date + fromProductName + createdAt (giây)
        $groups = [];
        foreach ($rows as $row) {
            // Key theo date + fromProductName + timestamp (giây) để gộp các dòng cùng lần chiết
            $key = ($row['date'] ?? '') . '|' . ($row['fromProductName'] ?? '') . '|' . ($row['createdAt'] ?? '');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id'              => $row['id'],
                    'rowIds'          => [$row['id']],
                    'date'            => $row['date']            ?? '',
                    'fromProductName' => $row['fromProductName'] ?? '',
                    'fromProductId'   => $row['fromProductId']   ?? null,
                    'fromQuantity'    => (float) ($row['fromQuantity'] ?? 0),
                    'note'            => $row['note']            ?? '',
                    'createdAt'       => $row['createdAt']       ?? null,
                    'updatedAt'       => $row['updatedAt']       ?? null,
                    'toItems'         => [],
                ];
            } else {
                $groups[$key]['rowIds'][] = $row['id'];
                if ((float) ($row['fromQuantity'] ?? 0) > 0) {
                    $groups[$key]['fromQuantity'] = (float) $row['fromQuantity'];
                }
                if (!empty($row['fromProductId'])) {
                    $groups[$key]['fromProductId'] = $row['fromProductId'];
                }
                if (!empty($row['fromProductName'])) {
                    $groups[$key]['fromProductName'] = $row['fromProductName'];
                }
            }

            // Thêm sản phẩm đích vào group
            if (!empty($row['toProductName']) || (float)($row['toQuantity'] ?? 0) > 0) {
                $groups[$key]['toItems'][] = [
                    'id'            => $row['id'],
                    'toProductId'   => $row['toProductId']   ?? null,
                    'toProductName' => $row['toProductName'] ?? '',
                    'toQuantity'    => (float) ($row['toQuantity'] ?? 0),
                ];
            }
        }

        // Áp dụng limit trên số phiên chiết (không phải số rows)
        if ($limit !== null && $limit > 0) {
            $groups = array_slice($groups, 0, $limit, true);
        }

        return $groups;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM repackage_history WHERE id = ?", [$id]);
    }

    /**
     * Hoàn tác (rollback) một phiên chiết:
     * 1. Lấy thông tin các dòng trong DB theo $rowIds.
     * 2. Đảo ngược thay đổi tồn kho trên bảng products:
     *    - Hoàn trả lại tồn kho nguồn (+fromQuantity).
     *    - Thu hồi tồn kho đích (-toQuantity).
     * 3. Xóa các dòng trong repackage_history.
     *
     * @param string[] $rowIds
     * @throws \RuntimeException
     */
    public function rollbackSession(array $rowIds): void
    {
        $cleanIds = array_values(array_filter(array_map('strval', $rowIds)));
        if (empty($cleanIds)) {
            throw new \RuntimeException('Không có ID bản ghi nào để hoàn tác.');
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $rows = $this->fetchAll(
            "SELECT * FROM repackage_history WHERE id IN ($placeholders)",
            $cleanIds
        );

        if (empty($rows)) {
            throw new \RuntimeException('Không tìm thấy dữ liệu phiên chiết trong CSDL.');
        }

        $now = $this->ts();
        $sqlUpdateSource = "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?";
        $sqlUpdateTarget = "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?";
        $sqlDelete       = "DELETE FROM repackage_history WHERE id IN ($placeholders)";

        $this->db->transactional(function () use ($rows, $cleanIds, $sqlUpdateSource, $sqlUpdateTarget, $sqlDelete, $now): void {
            foreach ($rows as $row) {
                $fromProductId = !empty($row['fromProductId']) ? (string) $row['fromProductId'] : null;
                $toProductId   = !empty($row['toProductId']) ? (string) $row['toProductId'] : null;
                $fromQty       = (float) ($row['fromQuantity'] ?? 0);
                $toQty         = (float) ($row['toQuantity'] ?? 0);

                // 1. Hoàn trả tồn kho nguồn (cộng lại)
                if ($fromProductId !== null && $fromQty > 0) {
                    $this->execute($sqlUpdateSource, [$fromQty, $now, $fromProductId]);
                }

                // 2. Thu hồi tồn kho đích (trừ đi)
                if ($toProductId !== null && $toQty > 0) {
                    $this->execute($sqlUpdateTarget, [$toQty, $now, $toProductId]);
                }
            }

            // 3. Xóa các bản ghi lịch sử
            $this->execute($sqlDelete, $cleanIds);
        });
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    /**
     * Thêm 1 row lịch sử chiết (gọi từ ProductRepository::doRepackage trong transaction).
     *
     * @param array $data  Cần có: date, fromProductId, fromProductName,
     *                             toProductId, toProductName, fromQuantity, toQuantity, note
     */
    public function addRow(array $data): void
    {
        $now = $this->ts();
        $this->execute("
            INSERT INTO repackage_history
                (id, date, fromProductId, fromProductName, toProductId, toProductName,
                 fromQuantity, toQuantity, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $this->generateId(),
            $data['date']            ?? date('d-m-Y'),
            $data['fromProductId']   ?? null,
            $data['fromProductName'] ?? '',
            $data['toProductId']     ?? null,
            $data['toProductName']   ?? '',
            (float) ($data['fromQuantity'] ?? 0),
            (float) ($data['toQuantity']   ?? 0),
            $data['note']            ?? '',
            $data['createdAt']       ?? $now,
            $data['updatedAt']       ?? $now,
        ]);
    }

    /**
     * Lọc các rows chưa tồn tại trong DB (chống duplicate khi sync Sheets).
     *
     * @param array[] $rows  Rows từ Google Sheets (đã cast type)
     * @return array[]
     */
    public function filterNewRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $ids = array_filter(array_column($rows, 'id'));
        if (empty($ids)) {
            return $rows;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $existing = $this->fetchAll(
            "SELECT id FROM repackage_history WHERE id IN ($placeholders)",
            array_values($ids)
        );
        $existingIds = array_column($existing, 'id');

        return array_values(array_filter($rows, function (array $row) use ($existingIds): bool {
            $id = $row['id'] ?? '';
            return $id !== '' && !in_array($id, $existingIds, true);
        }));
    }

    /**
     * Import rows chiết hàng từ Google Sheets:
     * - Chèn vào repackage_history (giữ nguyên timestamps gốc).
     * - Cập nhật products.repackageStock:
     *   + Trừ kho sản phẩm nguồn (fromProductId) nếu fromQuantity > 0.
     *   + Cộng kho sản phẩm đích (toProductId) nếu toQuantity > 0.
     *
     * @param array[] $rows  Đã qua filterNewRows()
     */
    public function importFromSheets(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sqlInsert = "INSERT OR IGNORE INTO repackage_history
                        (id, date, fromProductId, fromProductName, toProductId, toProductName,
                         fromQuantity, toQuantity, note, createdAt, updatedAt)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $sqlUpdateSource = "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?";
        $sqlUpdateTarget = "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?";

        $this->db->transactional(function () use ($rows, $sqlInsert, $sqlUpdateSource, $sqlUpdateTarget): void {
            $newRows = $this->filterNewRows($rows);
            $now = $this->ts();

            foreach ($newRows as $row) {
                $id            = (string) ($row['id'] ?? '');
                $date          = (string) ($row['date'] ?? '');
                $fromProductId = !empty($row['fromProductId']) ? (string) $row['fromProductId'] : null;
                $fromProdName  = (string) ($row['fromProductName'] ?? '');
                $toProductId   = !empty($row['toProductId']) ? (string) $row['toProductId'] : null;
                $toProdName    = (string) ($row['toProductName'] ?? '');
                $fromQty       = (float) ($row['fromQuantity'] ?? 0);
                $toQty         = (float) ($row['toQuantity'] ?? 0);
                $note          = (string) ($row['note'] ?? '');
                $createdAt     = !empty($row['createdAt']) ? (int) $row['createdAt'] : $now;
                $updatedAt     = !empty($row['updatedAt']) ? (int) $row['updatedAt'] : $now;

                if ($id === '') {
                    continue;
                }

                // 1. Chèn vào lịch sử
                $this->execute($sqlInsert, [
                    $id,
                    $date,
                    $fromProductId,
                    $fromProdName,
                    $toProductId,
                    $toProdName,
                    $fromQty,
                    $toQty,
                    $note,
                    $createdAt,
                    $updatedAt,
                ]);

                // 2. Cập nhật tồn kho sản phẩm nguồn (nếu fromQty > 0)
                if ($fromProductId !== null && $fromQty > 0) {
                    $this->execute($sqlUpdateSource, [$fromQty, $updatedAt, $fromProductId]);
                }

                // 3. Cập nhật tồn kho sản phẩm đích (nếu toQty > 0)
                if ($toProductId !== null && $toQty > 0) {
                    $this->execute($sqlUpdateTarget, [$toQty, $updatedAt, $toProductId]);
                }
            }
        });
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}

