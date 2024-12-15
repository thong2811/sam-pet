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

        $productList = $this->getData();
        foreach ($productList as $productId => &$productData) {
            $sellingPrice = !empty($row['sellingPrice']) ? $row['sellingPrice'] : 0;
            $purchasePrice = !empty($row['purchasePrice']) ? $row['purchasePrice'] : 0;
            $initStock = !empty($row['initStock']) ? $row['initStock'] : 0;

            $productData['profit'] = $sellingPrice - $purchasePrice;
            $productData['importStock'] = $importStock[$productId] ?? 0;
            $productData['exportStock'] = $exportStock[$productId] ?? 0;
            $productData['remainStock'] = $initStock + $productData['importStock'] - $productData['exportStock'];

            $productData['action'] = sprintf('
                <button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>
                <a href="/product/edit/%s" class="btn btn-primary">Chỉnh sửa</a>
                ', $productId, $productId);
        }

        return $productList;
    }
}
