<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Product extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header'   => ['id', 'name', 'unit', 'sellingPrice', 'purchasePrice', 'initStock', 'repackageStock'],
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
            $repackageStock = !empty($productData['repackageStock']) ? $productData['repackageStock'] : 0;

            $productData['profit'] = $sellingPrice - $purchasePrice;
            $productData['importStock'] = $importStock[$productId] ?? 0;
            $productData['exportStock'] = $exportStock[$productId] ?? 0;
            $productData['remainStock'] = $initStock + $repackageStock + $productData['importStock'] - $productData['exportStock'];
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

    public function doRepackage($postData)
    {
        $date = $postData['date'] ?? date('d-m-Y');
        $content = "Nhập chiết hàng cho ngày $date.\nChi tiết:";
        $productData = $this->getData();

        $productIdBig = $postData['productId_big'] ?? null;
        $quantityBig = $postData['quantity_big'] ?? null;
        $remainStockBig = $postData['remainStock_big'] ?? null;
        $productBig = $productData[$productIdBig] ?? null;
        $productNameBig = $productBig['name'] ?? $productIdBig;
        if (is_null($productBig)) {
            throw new \Exception("Không thể chiết hàng do không tìm thấy sản phẩm chiết: $productIdBig");
        }
        $repackageStockBig = empty($productBig['repackageStock']) ? 0 : $productBig['repackageStock'];
        $repackageStockBigAfter = $repackageStockBig - $quantityBig;
        $remainStockBigAfter = $remainStockBig - $quantityBig;
        $productData[$productIdBig]['repackageStock'] = $repackageStockBigAfter;
        $content .= "\n\t-$quantityBig $productNameBig";
        $content .= " (Tồn hiện tại: $remainStockBig, Tồn sau khi chiết: $remainStockBigAfter,";
        $content .= " SL chiết hiện tại: $repackageStockBig, SL chiết cuối: $repackageStockBigAfter)";

        $productIdSmallList = $postData['productId_small'] ?? [];
        $quantitySmallList = $postData['quantity_small'] ?? [];
        $remainStockSmallList = $postData['remainStock_small'] ?? [];
        foreach ($productIdSmallList as $index => $productIdSmall) {
            $quantitySmall = $quantitySmallList[$index] ?? 0;
            $remainStockSmall = $remainStockSmallList[$index] ?? 0;
            if (empty($productIdSmall) || empty($quantitySmall)) {
                continue;
            }

            $productSmall = $productData[$productIdSmall] ?? null;
            $productNameSmall = $productSmall['name'] ?? $productIdBig;
            if (is_null($productSmall)) {
                throw new \Exception("Không thể chiết hàng do không tìm thấy sản phẩm được chiết: $productIdSmall");
            }
            $repackageStockSmall = empty($productSmall['repackageStock']) ? 0 : $productSmall['repackageStock'];
            $repackageStockSmallAfter = $repackageStockSmall + $quantitySmall;
            $remainStockSmallAfter = $remainStockSmall + $quantitySmall;
            $productData[$productIdSmall]['repackageStock'] = $repackageStockSmallAfter;
            $content .= "\n\t+$quantitySmall $productNameSmall";
            $content .= " (Tồn hiện tại: $remainStockSmall, Tồn sau khi chiết: $remainStockSmallAfter,";
            $content .= " SL chiết hiện tại: $repackageStockSmall, SL chiết cuối: $repackageStockSmallAfter)";
        }
        $this->saveData($productData);

        $repackageHistoryModel = new RepackageHistory();
        $repackageHistoryModel->addRow(['date' => $date, 'content' => $content]);
    }
}
