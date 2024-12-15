<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class ImportStock extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'productId', 'productName', 'quantity', 'purchasePrice', 'note'],
        'fileName' => 'import-stock.csv'
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
        $importStockIdList = $postData['importStockId'] ?? [];
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $noteList = $postData['note'] ?? [];
        $dateList = $postData['date'] ?? [];

        $rows = [];
        foreach ($importStockIdList as $index => $importStockId) {
            if (empty($importStockId)) {
                continue;
            }

            $rows[] = [
                'id' => $importStockId,
                'productId' => $productIdList[$index] ?? '',
                'quantity' => $quantityList[$index] ?? 1,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
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

    public function getDataToView() {
        $data = $this->getData();

        foreach ($data as $id => &$row) {
            $purchasePrice = $row['purchasePrice'] ?? 0;
            $quantity = $row['quantity'] ?? 0;
            $row['total'] = (int) $purchasePrice * (int) $quantity;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }

        return $data;
    }
}
