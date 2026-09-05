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
     * Phân loại các rows từ Google Sheets thành 3 nhóm:
     * - 'new': Các dòng chưa có trong DB (chèn mới).
     * - 'updated': Các dòng đã có trong DB nhưng có sự thay đổi về số lượng/thông tin (cần cập nhật & điều chỉnh tồn kho lệch).
     * - 'unchanged': Các dòng đã có trong DB và hoàn toàn khớp dữ liệu (bỏ qua).
     *
     * @param array[] $rows  Rows từ Google Sheets (đã cast type)
     * @return array{new: array[], updated: array[], unchanged: array[], allSync: array[]}
     */
    public function categorizeSyncRows(array $rows): array
    {
        if (empty($rows)) {
            return ['new' => [], 'updated' => [], 'unchanged' => [], 'allSync' => []];
        }

        $ids = array_filter(array_column($rows, 'id'));
        $existingMap = [];

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $existingRows = $this->fetchAll(
                "SELECT * FROM repackage_history WHERE id IN ($placeholders)",
                array_values($ids)
            );
            foreach ($existingRows as $er) {
                $existingMap[$er['id']] = $er;
            }
        }

        $newRows       = [];
        $updatedRows   = [];
        $unchangedRows = [];

        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            if (!isset($existingMap[$id])) {
                $row['_syncStatus'] = 'new';
                $newRows[] = $row;
            } else {
                $db = $existingMap[$id];
                $dbFromQty    = (float) ($db['fromQuantity'] ?? 0);
                $dbToQty      = (float) ($db['toQuantity'] ?? 0);
                $sheetFromQty = (float) ($row['fromQuantity'] ?? 0);
                $sheetToQty   = (float) ($row['toQuantity'] ?? 0);

                $dbFromPid    = (string) ($db['fromProductId'] ?? '');
                $sheetFromPid = (string) ($row['fromProductId'] ?? '');
                $dbToPid      = (string) ($db['toProductId'] ?? '');
                $sheetToPid   = (string) ($row['toProductId'] ?? '');

                $dbDate       = (string) ($db['date'] ?? '');
                $sheetDate    = (string) ($row['date'] ?? '');
                $dbNote       = (string) ($db['note'] ?? '');
                $sheetNote    = (string) ($row['note'] ?? '');

                $isChanged = abs($dbFromQty - $sheetFromQty) > 0.0001
                    || abs($dbToQty - $sheetToQty) > 0.0001
                    || $dbFromPid !== $sheetFromPid
                    || $dbToPid !== $sheetToPid
                    || $dbDate !== $sheetDate
                    || $dbNote !== $sheetNote;

                if ($isChanged) {
                    $row['_syncStatus']       = 'updated';
                    $row['_oldFromQuantity']  = $dbFromQty;
                    $row['_oldToQuantity']    = $dbToQty;
                    $row['_oldFromProductId'] = $dbFromPid;
                    $row['_oldToProductId']   = $dbToPid;
                    $row['_oldDate']          = $dbDate;
                    $row['_oldNote']          = $dbNote;
                    $updatedRows[] = $row;
                } else {
                    $row['_syncStatus'] = 'unchanged';
                    $unchangedRows[] = $row;
                }
            }
        }

        return [
            'new'       => $newRows,
            'updated'   => $updatedRows,
            'unchanged' => $unchangedRows,
            'allSync'   => array_merge($newRows, $updatedRows),
        ];
    }

    /**
     * Lọc các rows cần đồng bộ (bao gồm cả dòng mới & dòng đã sửa trên Sheets).
     *
     * @param array[] $rows  Rows từ Google Sheets (đã cast type)
     * @return array[]
     */
    public function filterNewRows(array $rows): array
    {
        return $this->categorizeSyncRows($rows)['allSync'];
    }

    /**
     * Import / Cập nhật rows chiết hàng từ Google Sheets:
     * - Dòng mới: Chèn vào repackage_history và trừ kho nguồn / cộng kho đích.
     * - Dòng cập nhật: Cập nhật repackage_history và điều chỉnh độ lệch tồn kho tương ứng.
     *
     * @param array[] $rows  Đã qua categorize / filter
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

        $sqlUpdateRow = "UPDATE repackage_history SET
                            date = ?, fromProductId = ?, fromProductName = ?,
                            toProductId = ?, toProductName = ?,
                            fromQuantity = ?, toQuantity = ?, note = ?, updatedAt = ?
                         WHERE id = ?";

        $sqlUpdateSourceDec = "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?";
        $sqlUpdateSourceInc = "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?";
        $sqlUpdateTargetInc = "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?";
        $sqlUpdateTargetDec = "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?";

        $this->db->transactional(function () use (
            $rows, $sqlInsert, $sqlUpdateRow,
            $sqlUpdateSourceDec, $sqlUpdateSourceInc,
            $sqlUpdateTargetInc, $sqlUpdateTargetDec
        ): void {
            $categorized = $this->categorizeSyncRows($rows);
            $now = $this->ts();

            // 1. Xử lý các dòng mới (NEW)
            foreach ($categorized['new'] as $row) {
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

                if ($id === '') continue;

                $this->execute($sqlInsert, [
                    $id, $date, $fromProductId, $fromProdName,
                    $toProductId, $toProdName, $fromQty, $toQty,
                    $note, $createdAt, $updatedAt
                ]);

                if ($fromProductId !== null && $fromQty > 0) {
                    $this->execute($sqlUpdateSourceDec, [$fromQty, $updatedAt, $fromProductId]);
                }

                if ($toProductId !== null && $toQty > 0) {
                    $this->execute($sqlUpdateTargetInc, [$toQty, $updatedAt, $toProductId]);
                }
            }

            // 2. Xử lý các dòng cập nhật (UPDATED)
            foreach ($categorized['updated'] as $row) {
                $id            = (string) ($row['id'] ?? '');
                $date          = (string) ($row['date'] ?? '');
                $fromProductId = !empty($row['fromProductId']) ? (string) $row['fromProductId'] : null;
                $fromProdName  = (string) ($row['fromProductName'] ?? '');
                $toProductId   = !empty($row['toProductId']) ? (string) $row['toProductId'] : null;
                $toProdName    = (string) ($row['toProductName'] ?? '');
                $newFromQty    = (float) ($row['fromQuantity'] ?? 0);
                $newToQty      = (float) ($row['toQuantity'] ?? 0);
                $oldFromQty    = (float) ($row['_oldFromQuantity'] ?? 0);
                $oldToQty      = (float) ($row['_oldToQuantity'] ?? 0);
                $oldFromPid    = !empty($row['_oldFromProductId']) ? (string) $row['_oldFromProductId'] : null;
                $oldToPid      = !empty($row['_oldToProductId']) ? (string) $row['_oldToProductId'] : null;
                $note          = (string) ($row['note'] ?? '');
                $updatedAt     = !empty($row['updatedAt']) ? (int) $row['updatedAt'] : $now;

                if ($id === '') continue;

                // Cập nhật lại row trong DB
                $this->execute($sqlUpdateRow, [
                    $date, $fromProductId, $fromProdName,
                    $toProductId, $toProdName, $newFromQty, $newToQty,
                    $note, $updatedAt, $id
                ]);

                // Điều chỉnh tồn kho NGUỒN:
                if ($fromProductId === $oldFromPid) {
                    $diffFrom = $newFromQty - $oldFromQty;
                    if ($fromProductId !== null && abs($diffFrom) > 0.0001) {
                        if ($diffFrom > 0) {
                            // Tăng thêm lượng chiết -> trừ thêm vào kho
                            $this->execute($sqlUpdateSourceDec, [$diffFrom, $updatedAt, $fromProductId]);
                        } else {
                            // Giảm bớt lượng chiết -> cộng trả lại kho
                            $this->execute($sqlUpdateSourceInc, [abs($diffFrom), $updatedAt, $fromProductId]);
                        }
                    }
                } else {
                    // Đổi mã sản phẩm nguồn: hoàn trả SP cũ, trừ SP mới
                    if ($oldFromPid !== null && $oldFromQty > 0) {
                        $this->execute($sqlUpdateSourceInc, [$oldFromQty, $updatedAt, $oldFromPid]);
                    }
                    if ($fromProductId !== null && $newFromQty > 0) {
                        $this->execute($sqlUpdateSourceDec, [$newFromQty, $updatedAt, $fromProductId]);
                    }
                }

                // Điều chỉnh tồn kho ĐÍCH:
                if ($toProductId === $oldToPid) {
                    $diffTo = $newToQty - $oldToQty;
                    if ($toProductId !== null && abs($diffTo) > 0.0001) {
                        if ($diffTo > 0) {
                            // Tăng số lượng tạo thành -> cộng thêm vào kho
                            $this->execute($sqlUpdateTargetInc, [$diffTo, $updatedAt, $toProductId]);
                        } else {
                            // Giảm số lượng tạo thành -> trừ bớt khỏi kho
                            $this->execute($sqlUpdateTargetDec, [abs($diffTo), $updatedAt, $toProductId]);
                        }
                    }
                } else {
                    // Đổi mã sản phẩm đích: thu hồi SP cũ, cộng SP mới
                    if ($oldToPid !== null && $oldToQty > 0) {
                        $this->execute($sqlUpdateTargetDec, [$oldToQty, $updatedAt, $oldToPid]);
                    }
                    if ($toProductId !== null && $newToQty > 0) {
                        $this->execute($sqlUpdateTargetInc, [$newToQty, $updatedAt, $toProductId]);
                    }
                }
            }
        });
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}

