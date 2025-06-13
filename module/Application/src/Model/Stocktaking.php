<?php

namespace Application\Model;

use Application\Library\LeagueCsv;
use Application\Service\CommonService;

class Stocktaking extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header'   => ['id', 'stocktaking'],
        'fileName' => 'stocktaking.csv'
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
        $productIdList = $postData['productId'] ?? [];
        $stocktakingList = $postData['stocktaking'] ?? [];
        $data = [];
        foreach ($productIdList as $index => $productId) {
            $data[] = [
                "id" => $productId,
                "stocktaking" => $stocktakingList[$index] ?? ""
            ];
        }
        $this->updateRows($data);
    }

    public function renewWarehouse() {
        $logger = CommonService::logger();
        $result = CommonService::backupDataToStocktaking();
        if ($result === false) {
            $logger->error("Backup không thành công !");
            return false;
        }

        // clear import and export
        $exportStockModel = new ExportStock();
        $exportStockModel->saveData([]);
        $importStockModel = new ImportStock();
        $importStockModel->saveData([]);

        // renew product stock
        $result = true;
        $stocktakingList = $this->getData();
        $productModel = new Product();
        $productList = $productModel->getData();
        foreach ($productList as $id => &$productData) {
            $productName = $productData["name"] ?? $id;
            if (!isset($stocktakingList[$id])) {
                $logger->error("Có lỗi khi khai báo tồn kho cho sản phẩm [$productName].");
                $result = false;
            }
            $productData["initStock"] = $stocktakingList[$id]["stocktaking"] ?? "0";
            $productData["repackageStock"] = "0";
        }
        $productModel->saveData($productList);

        // clear data stocktaking
        if ($result) {
            $this->saveData([]);
        }

        return $result;
    }
}
