<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * ReportRepository — thay thế Application\Model\Report
 */
class ReportRepository extends BaseRepository
{
    private const TABLE = 'reports';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
    }

    /**
     * Tổng hợp dữ liệu theo ngày để auto-fill form báo cáo.
     * Query trực tiếp từ các bảng nguồn — không cần load toàn bộ vào memory.
     */
    public function getDataByDate(string $date): array
    {
        // Pet shop revenue + profit từ export_stock
        $exportRow = $this->fetchOne("
            SELECT
                COALESCE(SUM(sellingPrice * quantity), 0)                   AS petShopRevenue,
                COALESCE(SUM((sellingPrice - purchasePrice) * quantity), 0) AS petShopProfit
            FROM export_stock
            WHERE date = ?
        ", [$date]);

        // VetCare
        $vetRow = $this->fetchOne("
            SELECT
                COALESCE(SUM(treatmentAmount), 0) AS treatmentRevenue,
                COALESCE(SUM(spaAmount), 0)       AS spaRevenue
            FROM vet_care
            WHERE date = ?
        ", [$date]);

        // Expenses — tách chi phí thường và tiết kiệm
        $expRow = $this->fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN type = '0' THEN amount ELSE 0 END), 0) AS expenses,
                COALESCE(SUM(CASE WHEN type = '1' THEN amount ELSE 0 END), 0) AS savings
            FROM expenses
            WHERE date = ?
        ", [$date]);

        $treatmentRevenue = (float) ($vetRow['treatmentRevenue'] ?? 0);

        return [
            'petShopRevenue'   => (float) ($exportRow['petShopRevenue']  ?? 0),
            'petShopProfit'    => (float) ($exportRow['petShopProfit']   ?? 0),
            'spaRevenue'       => (float) ($vetRow['spaRevenue']         ?? 0),
            'treatmentRevenue' => $treatmentRevenue,
            'treatmentProfit'  => $treatmentRevenue * VetCareRepository::TREATMENT_PROFIT_PERCENT,
            'expenses'         => (float) ($expRow['expenses']           ?? 0),
            'savings'          => (float) ($expRow['savings']            ?? 0),
        ];
    }

    /**
     * Trả về [$totals, $data] với computed fields.
     * Tương thích với Report::getDataToView().
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("SELECT * FROM reports ORDER BY createdAt DESC");

        $totalRevenue       = 0.0;
        $totalExpenses      = 0.0;
        $totalMissingAmount = 0.0;
        $data = [];

        foreach ($rows as $row) {
            $petShopRevenue   = (float) ($row['petShopRevenue']   ?? 0);
            $spaRevenue       = (float) ($row['spaRevenue']       ?? 0);
            $treatmentRevenue = (float) ($row['treatmentRevenue'] ?? 0);
            $expenses         = (float) ($row['expenses']         ?? 0);
            $missingAmount    = (float) ($row['missingAmount']    ?? 0);

            $row['revenue']         = $petShopRevenue + $spaRevenue + $treatmentRevenue;
            $row['treatmentProfit'] = $treatmentRevenue * VetCareRepository::TREATMENT_PROFIT_PERCENT;
            $row['remaining']       = $row['revenue'] - $expenses;

            $totalRevenue       += $row['revenue'];
            $totalExpenses      += $expenses;
            $totalMissingAmount += $missingAmount;

            $data[$row['id']] = $row;
        }

        $totals = [
            'totalRevenue'       => $totalRevenue,
            'totalExpenses'      => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount,
        ];

        return [$totals, $data];
    }

    /**
     * Trả về [$totals, $chartData] — time-series cho Highcharts.
     * date dd-mm-yyyy → Unix timestamp milliseconds (×1000).
     *
     * @param string $from  dd-mm-yyyy (rỗng = không giới hạn)
     * @param string $to    dd-mm-yyyy
     */
    public function getDataToViewChart(string $from = '', string $to = ''): array
    {
        $where  = '';
        $params = [];

        if ($from !== '' || $to !== '') {
            $parts = [];
            if ($from !== '') {
                $parts[]  = "SUBSTR(date,7,4)||SUBSTR(date,4,2)||SUBSTR(date,1,2) >= SUBSTR(?,7,4)||SUBSTR(?,4,2)||SUBSTR(?,1,2)";
                $params[] = $from; $params[] = $from; $params[] = $from;
            }
            if ($to !== '') {
                $parts[]  = "SUBSTR(date,7,4)||SUBSTR(date,4,2)||SUBSTR(date,1,2) <= SUBSTR(?,7,4)||SUBSTR(?,4,2)||SUBSTR(?,1,2)";
                $params[] = $to; $params[] = $to; $params[] = $to;
            }
            $where = 'WHERE ' . implode(' AND ', $parts);
        }

        $rows = $this->fetchAll("
            SELECT * FROM reports $where
            ORDER BY SUBSTR(date,7,4)||SUBSTR(date,4,2)||SUBSTR(date,1,2) ASC
        ", $params);

        $totalRevenue       = 0.0;
        $totalExpenses      = 0.0;
        $totalMissingAmount = 0.0;
        $chartData          = [];

        foreach ($rows as $row) {
            $date             = $row['date'] ?? '';
            $dt               = $date ? \DateTime::createFromFormat('d-m-Y', $date) : null;
            $dateMs           = $dt ? ($dt->getTimestamp() * 1000) : 0;

            $petShopRevenue   = (float) ($row['petShopRevenue']   ?? 0);
            $petShopProfit    = (float) ($row['petShopProfit']    ?? 0);
            $spaRevenue       = (float) ($row['spaRevenue']       ?? 0);
            $treatmentRevenue = (float) ($row['treatmentRevenue'] ?? 0);
            $expenses         = (float) ($row['expenses']         ?? 0);
            $savings          = (float) ($row['savings']          ?? 0);
            $missingAmount    = (float) ($row['missingAmount']    ?? 0);

            $revenue   = $petShopRevenue + $spaRevenue + $treatmentRevenue;
            $remaining = $revenue - $expenses;

            $totalRevenue       += $revenue;
            $totalExpenses      += $expenses;
            $totalMissingAmount += $missingAmount;

            $chartData['revenue'][]          = [$dateMs, (int) $revenue];
            $chartData['petShopRevenue'][]   = [$dateMs, (int) $petShopRevenue];
            $chartData['petShopProfit'][]    = [$dateMs, (int) $petShopProfit];
            $chartData['spaRevenue'][]       = [$dateMs, (int) $spaRevenue];
            $chartData['treatmentRevenue'][] = [$dateMs, (int) $treatmentRevenue];
            $chartData['expenses'][]         = [$dateMs, (int) $expenses];
            $chartData['savings'][]          = [$dateMs, (int) $savings];
            $chartData['remaining'][]        = [$dateMs, (int) $remaining];
        }

        $totals = [
            'totalRevenue'       => $totalRevenue,
            'totalExpenses'      => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount,
            'totalRemaining'     => $totalRevenue - $totalExpenses,
        ];

        return [$totals, $chartData];
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData): string
    {
        $id  = $this->generateId();
        $now = $this->ts();

        try {
            $this->execute("
                INSERT INTO reports
                    (id, date, petShopRevenue, petShopProfit, spaRevenue, treatmentRevenue,
                     expenses, savings, missingAmount, note, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $id,
                trim($postData['date']             ?? ''),
                (float) ($postData['petShopRevenue']   ?? 0),
                (float) ($postData['petShopProfit']    ?? 0),
                (float) ($postData['spaRevenue']       ?? 0),
                (float) ($postData['treatmentRevenue'] ?? 0),
                (float) ($postData['expenses']         ?? 0),
                (float) ($postData['savings']          ?? 0),
                (float) ($postData['missingAmount']    ?? 0),
                trim($postData['note']             ?? ''),
                $now, $now,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: reports.date')) {
                throw new \RuntimeException(
                    'Ngày ' . trim($postData['date'] ?? '') . ' đã có báo cáo. Hãy dùng chức năng Sửa để cập nhật.'
                );
            }
            throw $e;
        }

        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute("
            UPDATE reports SET
                date = ?, petShopRevenue = ?, petShopProfit = ?,
                spaRevenue = ?, treatmentRevenue = ?,
                expenses = ?, savings = ?, missingAmount = ?,
                note = ?, updatedAt = ?
            WHERE id = ?
        ", [
            trim($postData['date']             ?? ''),
            (float) ($postData['petShopRevenue']   ?? 0),
            (float) ($postData['petShopProfit']    ?? 0),
            (float) ($postData['spaRevenue']       ?? 0),
            (float) ($postData['treatmentRevenue'] ?? 0),
            (float) ($postData['expenses']         ?? 0),
            (float) ($postData['savings']          ?? 0),
            (float) ($postData['missingAmount']    ?? 0),
            trim($postData['note']             ?? ''),
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
