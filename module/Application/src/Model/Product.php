<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Product extends LeagueCsv
{
    const INVOICE_CHECK_TRUE = "1";
    const INVOICE_CHECK_FALSE = "0";

    public const CSV_CONSTRUCT = [
        'header'   => ['id', 'name', 'unit', 'sellingPrice', 'purchasePrice', 'initStock', 'repackageStock', 'invoiceCheck'],
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

    /**
     * Tính remainStock của một sản phẩm dựa trên dữ liệu server-side.
     * Không tin vào giá trị client gửi lên.
     */
    public function calcRemainStock(string $productId, array $productData): float
    {
        $initStock      = (float) ($productData['initStock']      ?? 0);
        $repackageStock = (float) ($productData['repackageStock'] ?? 0);

        $importStockModel = new ImportStock();
        $importStock      = $importStockModel->totalQuantityByProduct();

        $exportStockModel = new ExportStock();
        $exportStock      = $exportStockModel->totalQuantityByProduct();

        return $initStock + $repackageStock
            + (float) ($importStock[$productId] ?? 0)
            - (float) ($exportStock[$productId] ?? 0);
    }

    public function doRepackage($postData)
    {
        $date        = $postData['date'] ?? date('d-m-Y');
        $content     = "Nhập chiết hàng cho ngày $date.\nChi tiết:";
        $productData = $this->getData();

        $productIdBig  = $postData['productId_big'] ?? null;
        $quantityBig   = (float) ($postData['quantity_big'] ?? 0);

        $productBig     = $productData[$productIdBig] ?? null;
        $productNameBig = $productBig['name'] ?? $productIdBig;

        if (is_null($productBig)) {
            throw new \Exception("Không thể chiết hàng do không tìm thấy sản phẩm chiết: $productIdBig");
        }

        // Tính remainStock server-side, không dùng giá trị client gửi lên
        $remainStockBig = $this->calcRemainStock($productIdBig, $productBig);

        if ($remainStockBig < $quantityBig) {
            throw new \Exception(
                "Tồn kho không đủ để chiết. Hiện còn: $remainStockBig {$productBig['unit']}."
            );
        }

        $repackageStockBig      = (float) ($productBig['repackageStock'] ?? 0);
        $repackageStockBigAfter = $repackageStockBig - $quantityBig;
        $remainStockBigAfter    = $remainStockBig - $quantityBig;

        $productData[$productIdBig]['repackageStock'] = $repackageStockBigAfter;

        $content .= "\n\t-$quantityBig $productNameBig";
        $content .= " (Tồn hiện tại: $remainStockBig, Tồn sau khi chiết: $remainStockBigAfter,";
        $content .= " SL chiết hiện tại: $repackageStockBig, SL chiết cuối: $repackageStockBigAfter)";

        $productIdSmallList  = $postData['productId_small'] ?? [];
        $quantitySmallList   = $postData['quantity_small'] ?? [];

        foreach ($productIdSmallList as $index => $productIdSmall) {
            $quantitySmall = (float) ($quantitySmallList[$index] ?? 0);

            if (empty($productIdSmall) || $quantitySmall <= 0) {
                continue;
            }

            $productSmall     = $productData[$productIdSmall] ?? null;
            $productNameSmall = $productSmall['name'] ?? $productIdSmall;

            if (is_null($productSmall)) {
                throw new \Exception("Không thể chiết hàng do không tìm thấy sản phẩm được chiết: $productIdSmall");
            }

            // Tính remainStock server-side cho sản phẩm đích
            $remainStockSmall = $this->calcRemainStock($productIdSmall, $productSmall);

            $repackageStockSmall      = (float) ($productSmall['repackageStock'] ?? 0);
            $repackageStockSmallAfter = $repackageStockSmall + $quantitySmall;
            $remainStockSmallAfter    = $remainStockSmall + $quantitySmall;

            $productData[$productIdSmall]['repackageStock'] = $repackageStockSmallAfter;

            $content .= "\n\t+$quantitySmall $productNameSmall";
            $content .= " (Tồn hiện tại: $remainStockSmall, Tồn sau khi chiết: $remainStockSmallAfter,";
            $content .= " SL chiết hiện tại: $repackageStockSmall, SL chiết cuối: $repackageStockSmallAfter)";
        }

        $this->saveData($productData);

        $repackageHistoryModel = new RepackageHistory();
        $repackageHistoryModel->addRow(['date' => $date, 'content' => $content]);
    }

    public function doAddInvoiceCheck($postData) {
        $invoiceCheckList = $postData['invoiceCheckList'] ?? [];
        $productData = $this->getData();
        foreach ($invoiceCheckList as $id => $value) {
            if (isset($productData[$id])) {
                $productData[$id]['invoiceCheck'] = $value;
            }
        }
        $this->saveData($productData);
    }
}
