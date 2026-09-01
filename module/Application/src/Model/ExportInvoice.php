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

    /**
     * Build nội dung hóa đơn từ POST data.
     * Dùng chung cho doAdd và doEdit để tránh code clone.
     *
     * @return array{content: string, total: float}
     */
    private function buildInvoiceContent(array $postData): array
    {
        $productIdList     = $postData['productId']     ?? [];
        $quantityList      = $postData['quantity']      ?? [];
        $purchasePriceList = $postData['purchasePrice'] ?? [];
        $sellingPriceList  = $postData['sellingPrice']  ?? [];
        $productNameList   = $postData['productName']   ?? [];
        $descList          = $postData['desc']          ?? [];

        $productContent = [];
        $sumTotal = 0;

        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }
            $quantity     = (float) ($quantityList[$index]     ?? 0);
            $sellingPrice = (float) ($sellingPriceList[$index] ?? 0);
            $total        = $sellingPrice * $quantity;

            $productContent[] = [
                'productId'    => $productId,
                'productName'  => $productNameList[$index] ?? '',
                'desc'         => $descList[$index]        ?? '',
                'quantity'     => $quantity,
                'purchasePrice'=> (float) ($purchasePriceList[$index] ?? 0),
                'sellingPrice' => $sellingPrice,
                'total'        => $total,
            ];
            $sumTotal += $total;
        }

        $treatmentContent = [
            'desc'  => $postData['treatmentDesc']  ?? '',
            'total' => (float) ($postData['treatmentTotal'] ?? 0),
        ];
        $spaContent = [
            'desc'  => $postData['spaDesc']  ?? '',
            'total' => (float) ($postData['spaTotal'] ?? 0),
        ];
        $sumTotal += $treatmentContent['total'] + $spaContent['total'];

        return [
            'content' => json_encode([
                'product'   => $productContent,
                'spa'       => $spaContent,
                'treatment' => $treatmentContent,
            ]),
            'total' => $sumTotal,
        ];
    }

    public function doAdd($postData)
    {
        $built = $this->buildInvoiceContent($postData);

        $this->addRow([
            'date'    => $postData['date'] ?? '',
            'content' => $built['content'],
            'total'   => $built['total'],
        ]);
    }

    public function doEdit($postData)
    {
        $built = $this->buildInvoiceContent($postData);

        $this->updateRow([
            'id'      => $postData['id'] ?? '',
            'date'    => $postData['date'] ?? '',
            'content' => $built['content'],
            'total'   => $built['total'],
        ]);
    }

    public function getDataToView()
    {
        $data = $this->getData();
        foreach ($data as $id => &$row) {
            // action column được render phía client trong DataTable
        }
        return $data;
    }
}
