<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Model\PdfGenerator;

/**
 * ExportInvoiceRepository — thay thế Application\Model\ExportInvoice
 */
class ExportInvoiceRepository extends BaseRepository
{
    private const TABLE = 'export_invoices';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM export_invoices ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM export_invoices WHERE id = ?", [$id]);
    }

    public function getDataToView(): array
    {
        return $this->getData();
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData): string
    {
        $built = $this->buildInvoiceContent($postData);
        $id    = $this->generateId();
        $now   = $this->ts();

        $this->execute("
            INSERT INTO export_invoices (id, date, content, total, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [
            $id,
            trim($postData['date'] ?? ''),
            $built['content'],
            $built['total'],
            $now, $now,
        ]);

        return $id;
    }

    public function doEdit(array $postData): void
    {
        $built = $this->buildInvoiceContent($postData);

        $this->execute("
            UPDATE export_invoices SET date = ?, content = ?, total = ?, updatedAt = ?
            WHERE id = ?
        ", [
            trim($postData['date'] ?? ''),
            $built['content'],
            $built['total'],
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }

    // ----------------------------------------------------------------
    // PDF
    // ----------------------------------------------------------------

    /**
     * Tạo PDF cho hóa đơn — giữ nguyên logic từ ExportInvoice::generatePdf().
     *
     * @throws \RuntimeException nếu không tìm thấy hóa đơn
     */
    public function generatePdf(string $id): string
    {
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

        $spaTotal = (float) ($spaContent['total'] ?? 0);
        if ($spaTotal > 0) {
            $pdfData[] = [
                'desc'  => $spaContent['desc'] ?? 'Dịch vụ Spa',
                'total' => $spaTotal,
            ];
        }

        $treatmentTotal = (float) ($treatmentContent['total'] ?? 0);
        if ($treatmentTotal > 0) {
            $pdfData[] = [
                'desc'  => $treatmentContent['desc'] ?? 'Dịch vụ Điều trị',
                'total' => $treatmentTotal,
            ];
        }

        foreach ($productContent as $item) {
            $itemTotal = (float) ($item['total']
                ?? ((float) ($item['sellingPrice'] ?? 0) * (float) ($item['quantity'] ?? 0)));
            if ($itemTotal <= 0) {
                continue;
            }
            $pdfData[] = [
                'desc'  => !empty($item['desc']) ? $item['desc'] : ($item['productName'] ?? ''),
                'total' => $itemTotal,
            ];
        }

        $pdfGenerator = new PdfGenerator();
        return $pdfGenerator->generate($date, $pdfData);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Build JSON content + tính tổng — dùng chung cho doAdd/doEdit.
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
        $sumTotal       = 0.0;

        foreach ($productIdList as $index => $productId) {
            if (empty($productId)) {
                continue;
            }
            $quantity     = (float) ($quantityList[$index]      ?? 0);
            $sellingPrice = (float) ($sellingPriceList[$index]  ?? 0);
            $total        = $sellingPrice * $quantity;

            $productContent[] = [
                'productId'     => $productId,
                'productName'   => $productNameList[$index]   ?? '',
                'desc'          => $descList[$index]          ?? '',
                'quantity'      => $quantity,
                'purchasePrice' => (float) ($purchasePriceList[$index] ?? 0),
                'sellingPrice'  => $sellingPrice,
                'total'         => $total,
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
            ], JSON_UNESCAPED_UNICODE),
            'total' => $sumTotal,
        ];
    }
}
