<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * ExportStockRepository — thay thế Application\Model\ExportStock
 */
class ExportStockRepository extends BaseRepository
{
    private const TABLE = 'export_stock';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM export_stock ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataByDate(string $date): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM export_stock WHERE date = ? ORDER BY createdAt ASC",
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
        return $this->fetchOne("SELECT * FROM export_stock WHERE id = ?", [$id]);
    }

    /**
     * [productId => totalQuantity]
     */
    public function totalQuantityByProduct(): array
    {
        $rows = $this->fetchAll(
            "SELECT productId, SUM(quantity) AS total FROM export_stock GROUP BY productId"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['productId']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * [date => ['revenue' => ..., 'profit' => ...]]
     */
    public function totalAmountByDate(): array
    {
        $rows = $this->fetchAll("
            SELECT date,
                   SUM(sellingPrice * quantity)                        AS revenue,
                   SUM((sellingPrice - purchasePrice) * quantity)      AS profit
            FROM export_stock
            GROUP BY date
        ");
        $result = [];
        foreach ($rows as $row) {
            $result[$row['date']] = [
                'revenue' => (float) $row['revenue'],
                'profit'  => (float) $row['profit'],
            ];
        }
        return $result;
    }

    /**
     * Thêm computed field `total = sellingPrice * quantity`.
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("SELECT * FROM export_stock ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $row['total'] = (float) $row['sellingPrice'] * (float) $row['quantity'];
            $data[$row['id']] = $row;
        }
        return $data;
    }

    /**
     * Gộp các dòng cùng productId (+sellingPrice) để lập hóa đơn.
     * Bỏ qua sản phẩm có invoiceCheck = '0' nếu $skip = true.
     *
     * @param array $exportStockList  Kết quả getDataByDate()
     * @param array $productList      Kết quả ProductRepository::getData()
     */
    public function mergeExportStockByItem(
        array $exportStockList,
        array $productList,
        bool $skip = true
    ): array {
        $data = [];
        foreach ($exportStockList as $row) {
            $productId    = $row['productId']    ?? '';
            $sellingPrice = (float) ($row['sellingPrice'] ?? 0);
            if (empty($productId)) {
                continue;
            }
            $invoiceCheck = $productList[$productId]['invoiceCheck'] ?? ProductRepository::INVOICE_CHECK_TRUE;
            if ($skip && $invoiceCheck === ProductRepository::INVOICE_CHECK_FALSE) {
                continue;
            }
            $key = $productId . '_' . $sellingPrice;
            if (isset($data[$key])) {
                $data[$key]['quantity'] += (float) ($row['quantity'] ?? 0);
            } else {
                $data[$key] = [
                    'productId'    => $productId,
                    'productName'  => $row['productName']  ?? '',
                    'quantity'     => (float) ($row['quantity']     ?? 0),
                    'purchasePrice'=> (float) ($row['purchasePrice'] ?? 0),
                    'sellingPrice' => $sellingPrice,
                ];
            }
        }
        return array_values($data);
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
            "SELECT id FROM export_stock WHERE id IN ($placeholders)",
            array_values($ids)
        );
        $existingIds = array_column($existing, 'id');

        return array_values(array_filter($rows, function (array $row) use ($existingIds): bool {
            $id = $row['id'] ?? '';
            return $id !== '' && !in_array($id, $existingIds, true);
        }));
    }

    /**
     * Import rows từ Google Sheets — preserve createdAt/updatedAt gốc.
     *
     * @param array[] $rows  Đã qua filterNewRows()
     */
    public function importFromSheets(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sql = "INSERT OR IGNORE INTO export_stock
                    (id, date, productId, productName, quantity, sellingPrice, purchasePrice, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->transactional(function () use ($rows, $sql): void {
            // Lọc lại lần 2 trong transaction để chống race condition
            $newRows = $this->filterNewRows($rows);
            foreach ($newRows as $row) {
                $now = $this->ts();
                $this->execute($sql, [
                    $row['id'],
                    $row['date']          ?? '',
                    $row['productId']     ?? '',
                    $row['productName']   ?? '',
                    (float) ($row['quantity']      ?? 0),
                    (float) ($row['sellingPrice']  ?? 0),
                    (float) ($row['purchasePrice'] ?? 0),
                    $row['note']          ?? '',
                    // Giữ nguyên timestamps gốc từ Sheets
                    !empty($row['createdAt']) ? (int) $row['createdAt'] : $now,
                    !empty($row['updatedAt']) ? (int) $row['updatedAt'] : $now,
                ]);
            }
        });
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData, array $productNameList): void
    {
        $productIdList     = $postData['productId']     ?? [];
        $quantityList      = $postData['quantity']      ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList  = $postData['sellingPrice']  ?? [];
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
                'sellingPrice'  => (float) ($sellingPriceList[$index]  ?? 0),
                'purchasePrice' => (float) ($purchasePriceList[$index] ?? 0),
                'note'          => $noteList[$index] ?? '',
            ];
        }

        if (empty($rows)) {
            return;
        }

        $sql = "INSERT INTO export_stock
                    (id, date, productId, productName, quantity, sellingPrice, purchasePrice, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $this->db->transactional(function () use ($rows, $sql): void {
            $now = $this->ts();
            foreach ($rows as $row) {
                $this->execute($sql, [
                    $this->generateId(),
                    $row['date'], $row['productId'], $row['productName'],
                    $row['quantity'], $row['sellingPrice'], $row['purchasePrice'],
                    $row['note'], $now, $now,
                ]);
            }
        });
    }

    /**
     * Edit: phân loại rows thành add / update / delete,
     * xử lý trong 1 transaction.
     */
    public function doEdit(array $postData, array $productNameList): void
    {
        $dateList            = $postData['date']          ?? [];
        $exportStockIdList   = $postData['exportStockId'] ?? [];
        $productIdList       = $postData['productId']     ?? [];
        $quantityList        = $postData['quantity']      ?? [];
        $purchasePriceList   = $postData['purchasePrice'] ?? [];
        $sellingPriceList    = $postData['sellingPrice']  ?? [];
        $noteList            = $postData['note']          ?? [];

        $rowsAdd    = [];
        $rowsUpdate = [];
        $rowsDelete = [];

        foreach ($dateList as $index => $date) {
            $exportStockId = $exportStockIdList[$index] ?? null;
            $productId     = $productIdList[$index]     ?? '';

            if (empty($productId)) {
                if (!empty($exportStockId)) {
                    $rowsDelete[] = $exportStockId;
                }
                continue;
            }

            $row = [
                'id'            => $exportStockId,
                'date'          => $date,
                'productId'     => $productId,
                'productName'   => $productNameList[$productId] ?? '',
                'quantity'      => (float) ($quantityList[$index]      ?? 1),
                'sellingPrice'  => (float) ($sellingPriceList[$index]  ?? 0),
                'purchasePrice' => (float) ($purchasePriceList[$index] ?? 0),
                'note'          => $noteList[$index] ?? '',
            ];

            if (empty($exportStockId)) {
                $rowsAdd[] = $row;
            } else {
                $rowsUpdate[] = $row;
            }
        }

        $sqlInsert = "INSERT INTO export_stock
                        (id, date, productId, productName, quantity, sellingPrice, purchasePrice, note, createdAt, updatedAt)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $sqlUpdate = "UPDATE export_stock SET
                        date = ?, productId = ?, productName = ?,
                        quantity = ?, sellingPrice = ?, purchasePrice = ?,
                        note = ?, updatedAt = ?
                      WHERE id = ?";

        $this->db->transactional(function () use (
            $rowsAdd, $rowsUpdate, $rowsDelete, $sqlInsert, $sqlUpdate
        ): void {
            $now = $this->ts();
            foreach ($rowsAdd as $row) {
                $this->execute($sqlInsert, [
                    $this->generateId(),
                    $row['date'], $row['productId'], $row['productName'],
                    $row['quantity'], $row['sellingPrice'], $row['purchasePrice'],
                    $row['note'], $now, $now,
                ]);
            }
            foreach ($rowsUpdate as $row) {
                $this->execute($sqlUpdate, [
                    $row['date'], $row['productId'], $row['productName'],
                    $row['quantity'], $row['sellingPrice'], $row['purchasePrice'],
                    $row['note'], $now, $row['id'],
                ]);
            }
            foreach ($rowsDelete as $id) {
                $this->execute("DELETE FROM export_stock WHERE id = ?", [$id]);
            }
        });
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
