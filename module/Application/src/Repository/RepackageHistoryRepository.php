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
                    'date'            => $row['date']            ?? '',
                    'fromProductName' => $row['fromProductName'] ?? '',
                    'fromProductId'   => $row['fromProductId']   ?? null,
                    'fromQuantity'    => (float) ($row['fromQuantity'] ?? 0),
                    'note'            => $row['note']            ?? '',
                    'createdAt'       => $row['createdAt']       ?? null,
                    'updatedAt'       => $row['updatedAt']       ?? null,
                    'toItems'         => [],
                ];
            }

            // Thêm sản phẩm đích vào group
            if (!empty($row['toProductName']) || (float)($row['toQuantity'] ?? 0) > 0) {
                $groups[$key]['toItems'][] = [
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

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
