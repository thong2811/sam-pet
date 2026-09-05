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
     * Phân loại các rows từ Google Sheets thành 3 nhóm:
     * - 'new': Các dòng chưa có trong DB (chèn mới).
     * - 'updated': Các dòng đã có trong DB nhưng có sự thay đổi về số lượng/giá/thông tin (cần cập nhật).
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
                "SELECT * FROM export_stock WHERE id IN ($placeholders)",
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
                $dbQty           = (float) ($db['quantity'] ?? 0);
                $dbSellPrice     = (float) ($db['sellingPrice'] ?? 0);
                $dbPurchasePrice = (float) ($db['purchasePrice'] ?? 0);
                $sheetQty        = (float) ($row['quantity'] ?? 0);
                $sheetSellPrice  = (float) ($row['sellingPrice'] ?? 0);
                $sheetPurchPrice = (float) ($row['purchasePrice'] ?? 0);

                $dbPid           = (string) ($db['productId'] ?? '');
                $sheetPid        = (string) ($row['productId'] ?? '');
                $dbPname         = (string) ($db['productName'] ?? '');
                $sheetPname      = (string) ($row['productName'] ?? '');
                $dbDate          = (string) ($db['date'] ?? '');
                $sheetDate       = (string) ($row['date'] ?? '');
                $dbNote          = (string) ($db['note'] ?? '');
                $sheetNote       = (string) ($row['note'] ?? '');

                $isChanged = abs($dbQty - $sheetQty) > 0.0001
                    || abs($dbSellPrice - $sheetSellPrice) > 0.0001
                    || abs($dbPurchasePrice - $sheetPurchPrice) > 0.0001
                    || $dbPid !== $sheetPid
                    || $dbPname !== $sheetPname
                    || $dbDate !== $sheetDate
                    || $dbNote !== $sheetNote;

                if ($isChanged) {
                    $row['_syncStatus']       = 'updated';
                    $row['_oldQuantity']      = $dbQty;
                    $row['_oldSellingPrice']  = $dbSellPrice;
                    $row['_oldPurchasePrice'] = $dbPurchasePrice;
                    $row['_oldProductId']     = $dbPid;
                    $row['_oldProductName']   = $dbPname;
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
     * Import / Cập nhật rows xuất hàng từ Google Sheets — preserve createdAt/updatedAt gốc.
     *
     * @param array[] $rows  Đã qua categorize / filter
     */
    public function importFromSheets(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $sqlInsert = "INSERT OR IGNORE INTO export_stock
                        (id, date, productId, productName, quantity, sellingPrice, purchasePrice, note, createdAt, updatedAt)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $sqlUpdate = "UPDATE export_stock SET
                        date = ?, productId = ?, productName = ?,
                        quantity = ?, sellingPrice = ?, purchasePrice = ?,
                        note = ?, updatedAt = ?
                      WHERE id = ?";

        $this->db->transactional(function () use ($rows, $sqlInsert, $sqlUpdate): void {
            $categorized = $this->categorizeSyncRows($rows);
            $now = $this->ts();

            // 1. Chèn dòng mới (NEW)
            foreach ($categorized['new'] as $row) {
                $this->execute($sqlInsert, [
                    $row['id'],
                    $row['date']          ?? '',
                    $row['productId']     ?? '',
                    $row['productName']   ?? '',
                    (float) ($row['quantity']      ?? 0),
                    (float) ($row['sellingPrice']  ?? 0),
                    (float) ($row['purchasePrice'] ?? 0),
                    $row['note']          ?? '',
                    !empty($row['createdAt']) ? (int) $row['createdAt'] : $now,
                    !empty($row['updatedAt']) ? (int) $row['updatedAt'] : $now,
                ]);
            }

            // 2. Cập nhật dòng đã sửa (UPDATED)
            foreach ($categorized['updated'] as $row) {
                $this->execute($sqlUpdate, [
                    $row['date']          ?? '',
                    $row['productId']     ?? '',
                    $row['productName']   ?? '',
                    (float) ($row['quantity']      ?? 0),
                    (float) ($row['sellingPrice']  ?? 0),
                    (float) ($row['purchasePrice'] ?? 0),
                    $row['note']          ?? '',
                    !empty($row['updatedAt']) ? (int) $row['updatedAt'] : $now,
                    $row['id'],
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
