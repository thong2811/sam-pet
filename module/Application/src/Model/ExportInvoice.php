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
        $date = $data['date'] ?? '';
        $content = $data['content'] ?? '';
        $content = json_decode($content, true);

        $productContent = $content['product'] ?? [];
        $treatmentContent = $content['treatment'] ?? [];
        $spaContent = $content['spa'] ?? [];

        $pdfData = [
            ['date' => $date, 'desc' => $spaContent['desc'], 'total' => $spaContent['total']],
            ['date' => $date, 'desc' => $treatmentContent['desc'], 'total' => $treatmentContent['total']],
        ];

        foreach ($productContent as $productData) {
            $pdfData[] = ['date' => $date, 'desc' => $productData['desc'], 'total' => $productData['total']];
        }

        $pdfGeneratorModel = new PdfGenerator();
        return $pdfGeneratorModel->generate($pdfData);
    }

    public function doAdd($postData)
    {
        $date = $postData['date'] ?? '';
        $productIdList = $postData['productId'] ?? [];
        $quantityList = $postData['quantity'] ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList = $postData['sellingPrice'] ?? [];
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

    public function getDataToView() {
        $data = $this->getData();
        foreach ($data as $id => &$row) {
            $row['action'] = sprintf('<button class="btn btn-secondary" onclick="reviewPdf(\'%s\')"> Xem PDF </button>', $id);
            $row['action'] .= sprintf('<button class="btn btn-danger ms-2" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }
        return $data;
    }
}
