<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class ExportStock extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'productId', 'productName', 'quantity', 'sellingPrice', 'purchasePrice', 'note'],
        'fileName' => 'export-stock.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function doAdd($postData)
    {
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
        $noteList = $postData['note'] ?? [];
        $dateList = $postData['date'] ?? [];

        $productModel = new Product();
        $productNameList = $productModel->getProductNameList();

        $rows = [];
        foreach ($dateList as $index => $date) {
            $productId = $productIdList[$index] ?? '';

            if (empty($productId)) {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'productId' => $productId,
                'productName' => $productNameList[$productId] ?? '',
                'quantity' => $quantityList[$index] ?? 1,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'note' => $noteList[$index] ?? '',
            ];
        }

        if (count($rows)) {
            $this->addRows($rows);
        }
    }

    public function doEdit($postData)
    {
        $dateList = $postData['date'] ?? [];
        $exportStockIdList = $postData['exportStockId'] ?? [];
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
        $noteList = $postData['note'] ?? [];

        $productModel = new Product();
        $productNameList = $productModel->getProductNameList();


        $rowsAdd = [];
        $rowsUpdate = [];
        $rowsDelete = [];
        foreach ($dateList as $index => $date) {
            $exportStockId = $exportStockIdList[$index] ?? null;
            $productId = $productIdList[$index] ?? '';

            if (empty($productId)) {
                $rowsDelete[] = $exportStockId;
                continue;
            }

            $row = [
                'date' => $date ?? '',
                'id' => $exportStockId,
                'productId' => $productId,
                'productName' => $productNameList[$productId] ?? '',
                'quantity' => $quantityList[$index] ?? 1,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'note' => $noteList[$index] ?? '',
            ];

            if (is_null($exportStockId)) {
                $rowsAdd[] = $row;
            } else {
                $rowsUpdate[] = $row;
            }
        }

        if (count($rowsAdd)) {
            $this->addRows($rowsAdd);
        }

        if (count($rowsUpdate)) {
            $this->updateRows($rowsUpdate);
        }

        if (count($rowsDelete)) {
            $this->deleteRows($rowsDelete);
        }

    }

    public function totalQuantityByProduct() {
        $data = $this->getData();

        $total = [];
        foreach ($data as $row) {
            $productId = $row['productId'] ?? null;
            $quantity = $row['quantity'] ?? null;

            if (is_null($productId) || !is_numeric($quantity)) {
                continue;
            }

            $sum = $total[$productId] ?? 0;
            $total[$productId] = $sum + $quantity;
        }

        return $total;
    }

    public function totalAmountByDate() {
        $data = $this->getData();

        $total = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? null;
            $sellingPrice = $row['sellingPrice'] ?? null;
            $purchasePrice = $row['purchasePrice'] ?? null;
            $profit = $sellingPrice - $purchasePrice;
            $quantity = $row['quantity'] ?? null;

            if (empty($date) || !is_numeric($sellingPrice) || !is_numeric($quantity)) {
                continue;
            }

            $sellingPriceSum = $total[$date]['revenue'] ?? 0;
            $total[$date]['revenue'] = $sellingPriceSum + ($sellingPrice * $quantity);

            $profitSum = $total[$date]['profit'] ?? 0;
            $total[$date]['profit'] = $profitSum + ($profit * $quantity);
        }

        return $total;
    }

    public function getDataToView() {
        $data = $this->getData();

        foreach ($data as $id => &$row) {
            $sellingPrice = $row['sellingPrice'] ?? 0;
            $quantity = $row['quantity'] ?? 0;
            $row['total'] = (int) $sellingPrice * (int) $quantity;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }

        return $data;
    }

    public function mergeExportStockByItem($exportStockList, $productList, $skipProductInvoiceCheckFalse = true) {
        $data = [];
        foreach ($exportStockList as $exportStockData) {
            $productId = $exportStockData['productId'] ?? '';
            $isInvoiceCheckFalse = isset($productList[$productId]['invoiceCheck']) && $productList[$productId]['invoiceCheck'] === Product::INVOICE_CHECK_FALSE;
            if ($skipProductInvoiceCheckFalse && $isInvoiceCheckFalse) {
                continue;
            }

            $sellingPrice = $exportStockData['sellingPrice'] ?? 0;
            if (empty($productId)) continue;

            if (isset($data[$productId]) && $data[$productId]['sellingPrice'] == $sellingPrice) {
                $data[$productId]['quantity'] += $exportStockData['quantity'] ?? 0;
            } else {
                $data[$productId] = [
                    'productName' => $exportStockData['productName'] ?? '',
                    'quantity' => $exportStockData['quantity'] ?? 0,
                    'purchasePrice' => $exportStockData['purchasePrice'] ?? 0,
                    'sellingPrice' => $exportStockData['sellingPrice'] ?? 0
                ];
            }
        }
        return $data;
    }

    /**
     * Trả về mảng tất cả id hiện có trong CSV.
     *
     * @return string[]
     */
    public function getExistingIds(): array
    {
        return array_keys($this->getData());
    }

    /**
     * Lọc ra các rows chưa tồn tại trong CSV (dựa theo field 'id').
     *
     * @param array[] $rows Danh sách rows từ Google Sheets
     * @return array[]
     */
    public function filterNewRows(array $rows): array
    {
        $existingIds = $this->getExistingIds();

        return array_values(array_filter($rows, function (array $row) use ($existingIds): bool {
            $id = $row['id'] ?? '';
            return $id !== '' && !in_array($id, $existingIds, true);
        }));
    }

    /**
     * Import rows từ Google Sheets vào CSV, bảo toàn createdAt/updatedAt gốc.
     *
     * Không dùng addRows() vì addRows() overwrite createdAt/updatedAt bằng time().
     * Method này merge trực tiếp vào data hiện tại rồi gọi saveData().
     *
     * @param array[] $rows Danh sách rows mới (đã qua filterNewRows)
     */
    public function importFromSheets(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $data = $this->getData();

        foreach ($rows as $row) {
            $id = $row['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            // Normalize fields theo headers của CSV, giữ nguyên createdAt/updatedAt từ Sheets
            $normalizedRow = $this->mappingDataWithHeaders($row);
            $data[$id] = $normalizedRow;
        }

        $this->saveData($data);
    }
}
