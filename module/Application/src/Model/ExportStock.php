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
}
