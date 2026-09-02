<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * ImportStockRepository — thay thế Application\Model\ImportStock
 */
class ImportStockRepository extends BaseRepository
{
    private const TABLE = 'import_stock';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /**
     * Tất cả rows keyed by id — dùng cho edit form.
     */
    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM import_stock ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    /**
     * Rows theo ngày cụ thể (dd-mm-yyyy).
     */
    public function getDataByDate(string $date): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM import_stock WHERE date = ? ORDER BY createdAt ASC",
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
        return $this->fetchOne("SELECT * FROM import_stock WHERE id = ?", [$id]);
    }

    /**
     * [productId => totalQuantity] — dùng cho tính remainStock.
     */
    public function totalQuantityByProduct(): array
    {
        $rows = $this->fetchAll(
            "SELECT productId, SUM(quantity) AS total FROM import_stock GROUP BY productId"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['productId']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Thêm computed field `total = purchasePrice * quantity`.
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("SELECT * FROM import_stock ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $row['total'] = (float) $row['purchasePrice'] * (float) $row['quantity'];
            $data[$row['id']] = $row;
        }
        return $data;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    /**
     * Batch insert nhiều dòng trong 1 transaction.
     * productNameList: [productId => name] — resolve từ ProductRepository.
     */
    public function doAdd(array $postData, array $productNameList): void
    {
        $productIdList     = $postData['productId']     ?? [];
        $quantityList      = $postData['quantity']      ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $noteList          = $postData['note']          ?? [];
        $dateList          = $postData['date']          ?? [];

        $rows = [];
        foreach ($dateList as $index => $date) {
            $productId = $productIdList[$index] ?? '';
            if (empty($productId)) {
                continue;
            }
            $rows[] = [
                'date'          => $date,
                'productId'     => $productId,
                'productName'   => $productNameList[$productId] ?? '',
                'quantity'      => (float) ($quantityList[$index]      ?? 1),
                'purchasePrice' => (float) ($purchasePriceList[$index] ?? 0),
                'note'          => $noteList[$index] ?? '',
            ];
        }

        if (empty($rows)) {
            return;
        }

        $sql = "INSERT INTO import_stock
                    (id, date, productId, productName, quantity, purchasePrice, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->transactional(function () use ($rows, $sql): void {
            $now = $this->ts();
            foreach ($rows as $row) {
                $this->execute($sql, [
                    $this->generateId(),
                    $row['date'],
                    $row['productId'],
                    $row['productName'],
                    $row['quantity'],
                    $row['purchasePrice'],
                    $row['note'],
                    $now,
                    $now,
                ]);
            }
        });
    }

    /**
     * Batch update — update các row có id, insert row mới (id null).
     */
    public function doEdit(array $postData, array $productNameList): void
    {
        $dateList            = $postData['date']          ?? [];
        $importStockIdList   = $postData['importStockId'] ?? [];
        $productIdList       = $postData['productId']     ?? [];
        $quantityList        = $postData['quantity']      ?? [];
        $purchasePriceList   = $postData['purchasePrice'] ?? [];
        $noteList            = $postData['note']          ?? [];

        $rowsAdd    = [];
        $rowsUpdate = [];

        foreach ($dateList as $index => $date) {
            $importStockId = $importStockIdList[$index] ?? null;
            $productId     = $productIdList[$index]     ?? '';
            if (empty($productId)) {
                continue;
            }
            $row = [
                'id'            => $importStockId,
                'date'          => $date,
                'productId'     => $productId,
                'productName'   => $productNameList[$productId] ?? '',
                'quantity'      => (float) ($quantityList[$index]      ?? 1),
                'purchasePrice' => (float) ($purchasePriceList[$index] ?? 0),
                'note'          => $noteList[$index] ?? '',
            ];
            if (empty($importStockId)) {
                $rowsAdd[] = $row;
            } else {
                $rowsUpdate[] = $row;
            }
        }

        $sqlInsert = "INSERT INTO import_stock
                        (id, date, productId, productName, quantity, purchasePrice, note, createdAt, updatedAt)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $sqlUpdate = "UPDATE import_stock SET
                        date = ?, productId = ?, productName = ?,
                        quantity = ?, purchasePrice = ?, note = ?, updatedAt = ?
                      WHERE id = ?";

        $this->db->transactional(function () use ($rowsAdd, $rowsUpdate, $sqlInsert, $sqlUpdate): void {
            $now = $this->ts();
            foreach ($rowsAdd as $row) {
                $this->execute($sqlInsert, [
                    $this->generateId(),
                    $row['date'], $row['productId'], $row['productName'],
                    $row['quantity'], $row['purchasePrice'], $row['note'],
                    $now, $now,
                ]);
            }
            foreach ($rowsUpdate as $row) {
                $this->execute($sqlUpdate, [
                    $row['date'], $row['productId'], $row['productName'],
                    $row['quantity'], $row['purchasePrice'], $row['note'],
                    $now, $row['id'],
                ]);
            }
        });
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
