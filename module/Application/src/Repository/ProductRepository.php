<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Database\Database;

/**
 * ProductRepository — thay thế Application\Model\Product
 *
 * remainStock tính bằng SQL JOIN thay vì load toàn bộ CSV vào PHP memory:
 *   remainStock = initStock + repackageStock
 *               + COALESCE(SUM(import), 0)
 *               - COALESCE(SUM(export), 0)
 */
class ProductRepository extends BaseRepository
{
    public const INVOICE_CHECK_TRUE  = '1';
    public const INVOICE_CHECK_FALSE = '0';

    private const TABLE = 'products';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /**
     * Trả về [$totals, $productList] — tương thích với Product::getDataToView().
     * remainStock tính bằng SQL JOIN, không load toàn bộ import/export vào memory.
     */
    public function getDataToView(): array
    {
        $sql = "
            SELECT
                p.*,
                COALESCE(i.importQty, 0)                         AS importStock,
                COALESCE(e.exportQty, 0)                         AS exportStock,
                (p.initStock + p.repackageStock
                    + COALESCE(i.importQty, 0)
                    - COALESCE(e.exportQty, 0))                  AS remainStock,
                (p.sellingPrice - p.purchasePrice)               AS profit
            FROM products p
            LEFT JOIN (
                SELECT productId, SUM(quantity) AS importQty
                FROM import_stock
                GROUP BY productId
            ) i ON i.productId = p.id
            LEFT JOIN (
                SELECT productId, SUM(quantity) AS exportQty
                FROM export_stock
                GROUP BY productId
            ) e ON e.productId = p.id
        ";

        $rows = $this->fetchAll($sql);

        $totalRemainStock_purchasePrice = 0.0;
        $totalRemainStock_sellingPrice  = 0.0;
        $productList = [];

        foreach ($rows as $row) {
            $remainStock   = (float) ($row['remainStock']   ?? 0);
            $sellingPrice  = (float) ($row['sellingPrice']  ?? 0);
            $purchasePrice = (float) ($row['purchasePrice'] ?? 0);

            $totalRemainStock_purchasePrice += $purchasePrice * $remainStock;
            $totalRemainStock_sellingPrice  += $sellingPrice  * $remainStock;

            // Format updatedAt cho hiển thị
            if (!empty($row['updatedAt'])) {
                $row['updatedAt'] = date('d-m-Y H:i:s', (int) $row['updatedAt']);
            }

            $productList[$row['id']] = $row;
        }

        $totals = [
            'totalRemainStock_purchasePrice' => $totalRemainStock_purchasePrice,
            'totalRemainStock_sellingPrice'  => $totalRemainStock_sellingPrice,
        ];

        return [$totals, $productList];
    }

    /**
     * Lấy tất cả sản phẩm dạng [id => row] (không tính remainStock).
     * Dùng cho dropdown chọn sản phẩm.
     */
    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM products");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    /**
     * [id => name] — dùng khi cần lookup tên nhanh.
     */
    public function getProductNameList(): array
    {
        $rows = $this->fetchAll("SELECT id, name FROM products");
        $list = [];
        foreach ($rows as $row) {
            $list[$row['id']] = $row['name'];
        }
        return $list;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM products WHERE id = ?", [$id]);
    }

    /**
     * Tính remainStock của một sản phẩm server-side (dùng trong doRepackage).
     */
    public function calcRemainStock(string $productId): float
    {
        $row = $this->fetchOne("
            SELECT
                p.initStock + p.repackageStock
                    + COALESCE(i.importQty, 0)
                    - COALESCE(e.exportQty, 0) AS remainStock
            FROM products p
            LEFT JOIN (
                SELECT productId, SUM(quantity) AS importQty
                FROM import_stock WHERE productId = ?
            ) i ON 1=1
            LEFT JOIN (
                SELECT productId, SUM(quantity) AS exportQty
                FROM export_stock WHERE productId = ?
            ) e ON 1=1
            WHERE p.id = ?
        ", [$productId, $productId, $productId]);

        return $row ? (float) $row['remainStock'] : 0.0;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData): string
    {
        $id  = $this->generateId();
        $now = $this->ts();

        $this->execute("
            INSERT INTO products
                (id, name, unit, sellingPrice, purchasePrice, initStock, repackageStock, invoiceCheck, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $id,
            trim($postData['name']          ?? ''),
            trim($postData['unit']          ?? ''),
            (float) ($postData['sellingPrice']  ?? 0),
            (float) ($postData['purchasePrice'] ?? 0),
            (float) ($postData['initStock']     ?? 0),
            (float) ($postData['repackageStock'] ?? 0),
            ($postData['invoiceCheck'] ?? self::INVOICE_CHECK_FALSE) === self::INVOICE_CHECK_TRUE
                ? self::INVOICE_CHECK_TRUE : self::INVOICE_CHECK_FALSE,
            $now,
            $now,
        ]);

        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute("
            UPDATE products SET
                name           = ?,
                unit           = ?,
                sellingPrice   = ?,
                purchasePrice  = ?,
                initStock      = ?,
                repackageStock = ?,
                invoiceCheck   = ?,
                updatedAt      = ?
            WHERE id = ?
        ", [
            trim($postData['name']          ?? ''),
            trim($postData['unit']          ?? ''),
            (float) ($postData['sellingPrice']  ?? 0),
            (float) ($postData['purchasePrice'] ?? 0),
            (float) ($postData['initStock']     ?? 0),
            (float) ($postData['repackageStock'] ?? 0),
            ($postData['invoiceCheck'] ?? self::INVOICE_CHECK_FALSE) === self::INVOICE_CHECK_TRUE
                ? self::INVOICE_CHECK_TRUE : self::INVOICE_CHECK_FALSE,
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }

    /**
     * Batch update invoiceCheck cho nhiều sản phẩm — dùng 1 transaction.
     * $invoiceCheckList: [productId => '0'|'1']
     */
    public function doAddInvoiceCheck(array $postData): void
    {
        $invoiceCheckList = $postData['invoiceCheckList'] ?? [];
        if (empty($invoiceCheckList)) {
            return;
        }

        $this->db->transactional(function () use ($invoiceCheckList): void {
            $now = $this->ts();
            foreach ($invoiceCheckList as $id => $value) {
                $check = ($value === self::INVOICE_CHECK_TRUE)
                    ? self::INVOICE_CHECK_TRUE
                    : self::INVOICE_CHECK_FALSE;
                $this->execute(
                    "UPDATE products SET invoiceCheck = ?, updatedAt = ? WHERE id = ?",
                    [$check, $now, $id]
                );
            }
        });
    }

    /**
     * Chiết hàng: trừ fromProduct, cộng toProduct(s).
     * Validate tồn kho server-side trước khi thực hiện.
     * Ghi lịch sử vào repackage_history (schema mới 5.15).
     *
     * @throws \RuntimeException nếu tồn kho không đủ hoặc sản phẩm không tồn tại
     */
    public function doRepackage(array $postData, RepackageHistoryRepository $historyRepo): void
    {
        $date           = $postData['date'] ?? date('d-m-Y');
        $productIdBig   = $postData['productId_big']  ?? null;
        $quantityBig    = (float) ($postData['quantity_big'] ?? 0);

        if (!$productIdBig) {
            throw new \RuntimeException('Không tìm thấy sản phẩm nguồn.');
        }

        $productBig = $this->getDataById($productIdBig);
        if (!$productBig) {
            throw new \RuntimeException("Không tìm thấy sản phẩm chiết: $productIdBig");
        }

        $remainStockBig = $this->calcRemainStock($productIdBig);
        if ($remainStockBig < $quantityBig) {
            throw new \RuntimeException(
                "Tồn kho không đủ để chiết. Hiện còn: $remainStockBig {$productBig['unit']}."
            );
        }

        $productIdSmallList = $postData['productId_small'] ?? [];
        $quantitySmallList  = $postData['quantity_small']  ?? [];

        // Validate tất cả sản phẩm đích trước khi ghi
        $smallItems = [];
        foreach ($productIdSmallList as $idx => $productIdSmall) {
            $quantitySmall = (float) ($quantitySmallList[$idx] ?? 0);
            if (empty($productIdSmall) || $quantitySmall <= 0) {
                continue;
            }
            $productSmall = $this->getDataById($productIdSmall);
            if (!$productSmall) {
                throw new \RuntimeException("Không tìm thấy sản phẩm đích: $productIdSmall");
            }
            $smallItems[] = [
                'id'       => $productIdSmall,
                'name'     => $productSmall['name'],
                'quantity' => $quantitySmall,
            ];
        }

        if (empty($smallItems)) {
            throw new \RuntimeException('Không có sản phẩm đích hợp lệ để chiết.');
        }

        $now = $this->ts();

        $this->db->transactional(function () use (
            $productIdBig, $productBig, $quantityBig,
            $smallItems, $date, $now, $historyRepo
        ): void {
            // Trừ repackageStock sản phẩm nguồn
            $this->execute(
                "UPDATE products SET repackageStock = repackageStock - ?, updatedAt = ? WHERE id = ?",
                [$quantityBig, $now, $productIdBig]
            );

            // Cộng repackageStock từng sản phẩm đích + ghi history
            foreach ($smallItems as $item) {
                $this->execute(
                    "UPDATE products SET repackageStock = repackageStock + ?, updatedAt = ? WHERE id = ?",
                    [$item['quantity'], $now, $item['id']]
                );

                $historyRepo->addRow([
                    'date'            => $date,
                    'fromProductId'   => $productIdBig,
                    'fromProductName' => $productBig['name'],
                    'toProductId'     => $item['id'],
                    'toProductName'   => $item['name'],
                    'fromQuantity'    => $quantityBig,
                    'toQuantity'      => $item['quantity'],
                    'note'            => '',
                ]);
            }
        });
    }
}
