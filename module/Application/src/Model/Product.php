<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Product extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header'   => ['id', 'name', 'unit', 'sellingPrice', 'purchasePrice', 'initStock'],
        'fileName' => 'product.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function doAdd($postData)
    {
        $this->addRow($postData);
    }

    public function doEdit($postData)
    {
        $this->updateRow($postData);
    }

    public function getProductNameList() {
        $productNameList = [];
        $data = $this->getData();

        foreach ($data as $id => $row) {
            $productNameList[$id] = $row['name'] ?? '';
        }

        return $productNameList;
    }

    public function getDataToView() {
        $importStockModel = new ImportStock();
        $importStock = $importStockModel->totalQuantityByProduct();

        $exportStockModel = new ExportStock();
        $exportStock = $exportStockModel->totalQuantityByProduct();

        $totalRemainStock_purchasePrice = 0;
        $totalRemainStock_sellingPrice = 0;

        $productList = $this->getData();
        foreach ($productList as $productId => &$productData) {
            $sellingPrice = !empty($productData['sellingPrice']) ? $productData['sellingPrice'] : 0;
            $purchasePrice = !empty($productData['purchasePrice']) ? $productData['purchasePrice'] : 0;
            $initStock = !empty($productData['initStock']) ? $productData['initStock'] : 0;

            $productData['profit'] = $sellingPrice - $purchasePrice;
            $productData['importStock'] = $importStock[$productId] ?? 0;
            $productData['exportStock'] = $exportStock[$productId] ?? 0;
            $productData['remainStock'] = $initStock + $productData['importStock'] - $productData['exportStock'];
            $productData['action'] = sprintf('
                <button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>
                <button class="btn btn-primary" onclick="openEditModal(\'%s\')">Chỉnh sửa</button>
                ', $productId, $productId);

            $totalRemainStock_purchasePrice += (int) $purchasePrice * $productData['remainStock'];
            $totalRemainStock_sellingPrice += (int) $sellingPrice * $productData['remainStock'];

            if (!empty($productData['updatedAt'])) {
                $productData['updatedAt'] = (\date('d-m-Y H:i:s', $productData['updatedAt']));
            }
        }

        $totals = [
            'totalRemainStock_purchasePrice' => $totalRemainStock_purchasePrice,
            'totalRemainStock_sellingPrice' => $totalRemainStock_sellingPrice
        ];

        return [$totals, $productList];
    }
}
