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

    public function getDataToView() {
        $importStockModel = new ImportStock();
        $importStock = $importStockModel->totalQuantityByProduct();

        $exportStockModel = new ExportStock();
        $exportStock = $exportStockModel->totalQuantityByProduct();

        $productList = $this->getData();
        foreach ($productList as $productId => &$productData) {
            $productData['importStock'] = $importStock[$productId] ?? 0;
            $productData['exportStock'] = $exportStock[$productId] ?? 0;
        }

        return $productList;
    }

    public function doAdd($postData)
    {
        $this->addRow($postData);
    }

    public function doEdit($postData)
    {
        $this->updateRow($postData);
    }
}
