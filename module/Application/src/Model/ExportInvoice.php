<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class ExportInvoice extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'content', 'total'],
        'fileName' => 'export-invoice.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function generatePdf($id) {
        $data = $this->getDataById($id);
        if (empty($data)) {
            throw new \RuntimeException("Không tìm thấy hóa đơn với id: $id");
        }

        $date    = $data['date'] ?? '';
        $content = json_decode($data['content'] ?? '{}', true);

        $productContent   = $content['product']   ?? [];
        $treatmentContent = $content['treatment'] ?? [];
        $spaContent       = $content['spa']       ?? [];

        $pdfData = [];

        // Spa — có thể lưu 'desc' hoặc không có desc thì bỏ qua
        $spaTotal = (float) ($spaContent['total'] ?? 0);
        if ($spaTotal > 0) {
            $pdfData[] = [
                'desc'  => $spaContent['desc'] ?? 'Dịch vụ Spa',
                'total' => $spaTotal,
            ];
        }

        // Điều trị
        $treatmentTotal = (float) ($treatmentContent['total'] ?? 0);
        if ($treatmentTotal > 0) {
            $pdfData[] = [
                'desc'  => $treatmentContent['desc'] ?? 'Dịch vụ Điều trị',
                'total' => $treatmentTotal,
            ];
        }

        // Sản phẩm — key lưu là 'productName', 'quantity', 'sellingPrice', 'total'
        foreach ($productContent as $item) {
            $itemTotal = (float) ($item['total'] ?? ((float)($item['sellingPrice'] ?? 0) * (float)($item['quantity'] ?? 0)));
            if ($itemTotal <= 0) {
                continue;
            }

            // Dùng 'desc' nếu có, fallback về productName + quantity + unit
            $desc = !empty($item['desc'])
                ? $item['desc']
                : ($item['productName'] ?? '');

            $pdfData[] = [
                'desc'  => $desc,
                'total' => $itemTotal,
            ];
        }

        $pdfGeneratorModel = new PdfGenerator();
        return $pdfGeneratorModel->generate($date, $pdfData);
    }

    public function doAdd($postData)
    {
        $date = $postData['date'] ?? '';
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
        $productName = $postData['productName'] ?? [];
        $desc = $postData['desc'] ?? [];

        $productContent = [];
        $sumTotal = 0;
        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }
            $quantity = $quantityList[$index] ?? 0;
            $sellingPrice = $sellingPriceList[$index] ?? 0;
            $total = $sellingPrice * $quantity;

            $productContent[] = [
                'productId' => $productId,
                'productName' => $productName[$index] ?? '',
                'desc' => $desc[$index] ?? '',
                'quantity' => $quantityList[$index] ?? 0,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'total' => $total
            ];
            $sumTotal += $total;
        }

        $treatmentContent = [
            'desc' => $postData['treatmentDesc'] ?? '',
            'total' => $postData['treatmentTotal'] ?? 0,
        ];
        $spaContent = [
            'desc' => $postData['spaDesc'] ?? '',
            'total' => $postData['spaTotal'] ?? 0,
        ];
        $sumTotal += $treatmentContent['total'] + $spaContent['total'];

        $this->addRow([
            'date' => $date,
            'content' => json_encode([
                'product' => $productContent,
                'spa' => $spaContent,
                'treatment' => $treatmentContent
            ]),
            'total' => $sumTotal
        ]);

    }

    public function doEdit($postData)
    {
        $id = $postData['id'] ?? '';
        $date = $postData['date'] ?? '';
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
        $productName = $postData['productName'] ?? [];
        $desc = $postData['desc'] ?? [];

        $productContent = [];
        $sumTotal = 0;
        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }
            $quantity = $quantityList[$index] ?? 0;
            $sellingPrice = $sellingPriceList[$index] ?? 0;
            $total = $sellingPrice * $quantity;

            $productContent[] = [
                'productId' => $productId,
                'productName' => $productName[$index] ?? '',
                'desc' => $desc[$index] ?? '',
                'quantity' => $quantityList[$index] ?? 0,
                'purchasePrice' => $purchasePriceList[$index] ?? 0,
                'sellingPrice' => $sellingPriceList[$index] ?? 0,
                'total' => $total
            ];
            $sumTotal += $total;
        }

        $treatmentContent = [
            'desc' => $postData['treatmentDesc'] ?? '',
            'total' => $postData['treatmentTotal'] ?? 0,
        ];
        $spaContent = [
            'desc' => $postData['spaDesc'] ?? '',
            'total' => $postData['spaTotal'] ?? 0,
        ];
        $sumTotal += $treatmentContent['total'] + $spaContent['total'];

        $this->updateRow([
            'id' => $id,
            'date' => $date,
            'content' => json_encode([
                'product' => $productContent,
                'spa' => $spaContent,
                'treatment' => $treatmentContent
            ]),
            'total' => $sumTotal
        ]);

    }

    public function getDataToView() {
        $data = $this->getData();
        foreach ($data as $id => &$row) {
            $row['action'] = sprintf('<button class="btn btn-secondary" onclick="reviewPdf(\'%s\')"> Xem PDF </button>', $id);
            $row['action'] .= sprintf('<a class="btn btn-primary ms-2" href="/export-invoice/edit/%s"> Sửa </a>', $id);
            $row['action'] .= sprintf('<button class="btn btn-danger ms-2" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }
        return $data;
    }
}
