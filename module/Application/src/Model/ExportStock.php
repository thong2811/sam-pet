<?php

namespace Application\Model;

use Application\Service\CsvService;

class ExportStock extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'productId', 'quantity', 'sellingPrice', 'purchasePrice', 'date', 'note'],
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

}
