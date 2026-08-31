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

        // Đọc dữ liệu hiện có một lần
        $existingData = $this->getData();

        $rows = [];
        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }
            $stocktakingValue = $stocktakingList[$index] ?? '';
            $now = time();

            if (isset($existingData[$productId])) {
                // Cập nhật row đã tồn tại
                $rows[$productId] = array_merge($existingData[$productId], [
                    'id'          => $productId,
                    'stocktaking' => $stocktakingValue,
                    'updatedAt'   => $now,
                ]);
            } else {
                // Tạo mới nếu chưa có (upsert)
                $rows[$productId] = [
                    'id'          => $productId,
                    'stocktaking' => $stocktakingValue,
                    'createdAt'   => $now,
                    'updatedAt'   => $now,
                ];
            }
        }

        $this->saveData($rows);
    }

    public function renewWarehouse(): bool
    {
        $logger = CommonService::logger();

        // Bước 1: Backup trước khi thay đổi bất cứ điều gì
        $backupResult = CommonService::backupDataToStocktaking();
        if ($backupResult === false) {
            $logger->error('renewWarehouse: Backup không thành công, hủy chốt kho.');
            return false;
        }

        // Chụp snapshot toàn bộ dữ liệu hiện tại để có thể rollback
        $exportStockModel  = new ExportStock();
        $importStockModel  = new ImportStock();
        $productModel      = new Product();

        $snapshotExport    = $exportStockModel->getData();
        $snapshotImport    = $importStockModel->getData();
        $snapshotProducts  = $productModel->getData();

        try {
            // Bước 2: Xóa lịch sử nhập/xuất
            $exportStockModel->saveData([]);
            $importStockModel->saveData([]);

            // Bước 3: Cập nhật tồn kho từng sản phẩm
            $stocktakingList = $this->getData();
            $productList     = $productModel->getData();

            foreach ($productList as $id => &$productData) {
                $productName = $productData['name'] ?? $id;
                if (!isset($stocktakingList[$id])) {
                    throw new \RuntimeException(
                        "Không tìm thấy số kiểm kê cho sản phẩm [$productName]. Hủy chốt kho."
                    );
                }
                $productData['initStock']      = $stocktakingList[$id]['stocktaking'] ?? '0';
                $productData['repackageStock'] = '0';
            }
            unset($productData);

            $productModel->saveData($productList);

            // Bước 4: Xóa dữ liệu kiểm kê
            $this->saveData([]);

            return true;

        } catch (\Throwable $e) {
            // Rollback: khôi phục lại dữ liệu snapshot
            $logger->error('renewWarehouse thất bại, đang rollback: ' . $e->getMessage());

            try {
                $exportStockModel->saveData($snapshotExport);
                $importStockModel->saveData($snapshotImport);
                $productModel->saveData($snapshotProducts);
                $logger->info('renewWarehouse: Rollback thành công.');
            } catch (\Throwable $rollbackError) {
                $logger->error('renewWarehouse: Rollback cũng thất bại: ' . $rollbackError->getMessage());
            }

            return false;
        }
    }
}
