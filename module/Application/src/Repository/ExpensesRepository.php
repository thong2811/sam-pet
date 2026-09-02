<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * ExpensesRepository — thay thế Application\Model\Expenses
 */
class ExpensesRepository extends BaseRepository
{
    private const TABLE = 'expenses';

    public const TYPE_OTHER   = '0';
    public const TYPE_SAVINGS = '1';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getDataByDate(string $date): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM expenses WHERE date = ? ORDER BY createdAt ASC",
            [$date]
        );
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM expenses WHERE id = ?", [$id]);
    }

    /**
     * Trả về [$expensesByDate, $savingsByDate]
     *   $expensesByDate:  [date => total_amount]  (type = 0)
     *   $savingsByDate:   [date => total_savings]  (type = 1)
     */
    public function totalAmountByDate(): array
    {
        $rows = $this->fetchAll("
            SELECT date, type, SUM(amount) AS total
            FROM expenses
            GROUP BY date, type
        ");

        $expensesByDate = [];
        $savingsByDate  = [];

        foreach ($rows as $row) {
            $date  = $row['date'];
            $total = (float) $row['total'];
            if ($row['type'] === self::TYPE_SAVINGS) {
                $savingsByDate[$date]  = ($savingsByDate[$date]  ?? 0) + $total;
            } else {
                $expensesByDate[$date] = ($expensesByDate[$date] ?? 0) + $total;
            }
        }

        return [$expensesByDate, $savingsByDate];
    }

    /**
     * Thêm computed field `typeText`.
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("SELECT * FROM expenses ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $row['typeText'] = $row['type'] === self::TYPE_SAVINGS ? 'Tiền tiết kiệm' : 'Khác';
            $data[$row['id']] = $row;
        }
        return $data;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    /**
     * Batch insert nhiều dòng trong 1 ngày.
     */
    public function doAdd(array $postData): void
    {
        $dateList   = $postData['date']   ?? [];
        $typeList   = $postData['type']   ?? [];
        $reasonList = $postData['reason'] ?? [];
        $amountList = $postData['amount'] ?? [];
        $personList = $postData['person'] ?? [];
        $noteList   = $postData['note']   ?? [];

        $rows = [];
        foreach ($dateList as $index => $date) {
            if (empty($date)) {
                continue;
            }
            $rows[] = [
                'date'   => $date,
                'type'   => ($typeList[$index]   ?? self::TYPE_OTHER) === self::TYPE_SAVINGS
                                ? self::TYPE_SAVINGS : self::TYPE_OTHER,
                'reason' => $reasonList[$index] ?? '',
                'amount' => (float) ($amountList[$index] ?? 0),
                'person' => $personList[$index] ?? '',
                'note'   => $noteList[$index]   ?? '',
            ];
        }

        if (empty($rows)) {
            return;
        }

        $sql = "INSERT INTO expenses
                    (id, date, type, reason, amount, person, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->transactional(function () use ($rows, $sql): void {
            $now = $this->ts();
            foreach ($rows as $row) {
                $this->execute($sql, [
                    $this->generateId(),
                    $row['date'], $row['type'], $row['reason'],
                    $row['amount'], $row['person'], $row['note'],
                    $now, $now,
                ]);
            }
        });
    }

    /**
     * Replace-all strategy: xóa rows cũ theo danh sách id,
     * insert lại với data mới, giữ createdAt gốc.
     */
    public function doEdit(array $postData): void
    {
        $expensesIdList = $postData['expensesId'] ?? [];
        $dateList       = $postData['date']       ?? [];
        $typeList       = $postData['type']       ?? [];
        $reasonList     = $postData['reason']     ?? [];
        $amountList     = $postData['amount']     ?? [];
        $personList     = $postData['person']     ?? [];
        $noteList       = $postData['note']       ?? [];

        // Lấy createdAt gốc của các row cũ để preserve
        $oldCreatedAt = [];
        foreach ($expensesIdList as $id) {
            if (!empty($id)) {
                $existing = $this->fetchOne(
                    "SELECT createdAt FROM expenses WHERE id = ?", [$id]
                );
                $oldCreatedAt[$id] = $existing['createdAt'] ?? null;
            }
        }

        $this->db->transactional(function () use (
            $expensesIdList, $dateList, $typeList, $reasonList,
            $amountList, $personList, $noteList, $oldCreatedAt
        ): void {
            $now = $this->ts();

            // Xóa rows cũ
            foreach ($expensesIdList as $id) {
                if (!empty($id)) {
                    $this->execute("DELETE FROM expenses WHERE id = ?", [$id]);
                }
            }

            // Insert lại
            $sql = "INSERT INTO expenses
                        (id, date, type, reason, amount, person, note, createdAt, updatedAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            foreach ($dateList as $index => $date) {
                if (empty($date)) {
                    continue;
                }
                $oldId      = $expensesIdList[$index] ?? null;
                $createdAt  = (!empty($oldId) && isset($oldCreatedAt[$oldId]))
                                ? ($oldCreatedAt[$oldId] ?? $now)
                                : $now;
                // Nếu row cũ có id thì giữ id, row mới thì sinh id mới
                $newId = !empty($oldId) ? $oldId : $this->generateId();

                $this->execute($sql, [
                    $newId,
                    $date,
                    ($typeList[$index] ?? self::TYPE_OTHER) === self::TYPE_SAVINGS
                        ? self::TYPE_SAVINGS : self::TYPE_OTHER,
                    $reasonList[$index] ?? '',
                    (float) ($amountList[$index] ?? 0),
                    $personList[$index] ?? '',
                    $noteList[$index]   ?? '',
                    $createdAt,
                    $now,
                ]);
            }
        });
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
