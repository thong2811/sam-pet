<?php

namespace Application\Model;

use Application\Service\CsvService;

class ExportStock extends CsvService
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

        $rows = [];
        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }

            $rows[] = [
                'productId' => $productId,
                'quantity' => $quantityList[$index] ?? 1,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'note' => $noteList[$index] ?? '',
                'date' => $dateList[$index] ?? '',
            ];
        }

        if (count($rows)) {
            $this->addRows($rows);
        }
    }

    public function doEdit($postData)
    {
        $exportStockIdList = $postData['exportStockId'] ?? [];
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
        $noteList = $postData['note'] ?? [];
        $dateList = $postData['date'] ?? [];

        $rows = [];
        foreach ($exportStockIdList as $index => $exportStockId) {
            if (empty($exportStockId)) {
                continue;
            }

            $rows[] = [
                'id' => $exportStockId,
                'productId' => $productIdList[$index] ?? '',
                'quantity' => $quantityList[$index] ?? 1,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'note' => $noteList[$index] ?? '',
                'date' => $dateList[$index] ?? '',
            ];
        }

        if (count($rows)) {
            $this->updateRows($rows);
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
}
