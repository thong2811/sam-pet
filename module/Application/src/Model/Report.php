<?php

namespace Application\Model;

use Application\Library\LeagueCsv;
use Application\Service\CommonService;

class Report extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'petShopRevenue', 'petShopProfit', 'spaRevenue', 'treatmentRevenue', 'expenses', 'savings', 'missingAmount', 'note'],
        'fileName' => 'report.csv'
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

    /**
     * Lấy dữ liệu tổng hợp theo ngày để auto-fill form báo cáo.
     * Trả về array gồm: petShopRevenue, petShopProfit, spaRevenue,
     * treatmentRevenue, treatmentProfit, expenses, savings của ngày đó.
     */
    public function getDataByDate(string $date): array
    {
        // ExportStock — doanh thu và lợi nhuận pet shop
        $exportStockModel            = new ExportStock();
        $exportStockTotalAmountByDate = $exportStockModel->totalAmountByDate();
        $petShopRevenue = (float) ($exportStockTotalAmountByDate[$date]['revenue'] ?? 0);
        $petShopProfit  = (float) ($exportStockTotalAmountByDate[$date]['profit']  ?? 0);

        // VetCare — spa và điều trị
        $vetCareModel            = new VetCare();
        $vetCareTotalAmountByDate = $vetCareModel->totalAmountByDate();
        $spaRevenue       = (float) ($vetCareTotalAmountByDate[$date]['spa']            ?? 0);
        $treatmentRevenue = (float) ($vetCareTotalAmountByDate[$date]['treatment']      ?? 0);
        $treatmentProfit  = (float) ($vetCareTotalAmountByDate[$date]['treatmentProfit'] ?? 0);

        // Expenses — chi phí và tiết kiệm
        $expensesModel            = new Expenses();
        [$expensesByDate, $savingsByDate] = $expensesModel->totalAmountByDate();
        $expenses = (float) ($expensesByDate[$date] ?? 0);
        $savings  = (float) ($savingsByDate[$date]  ?? 0);

        return [
            'petShopRevenue'   => $petShopRevenue,
            'petShopProfit'    => $petShopProfit,
            'spaRevenue'       => $spaRevenue,
            'treatmentRevenue' => $treatmentRevenue,
            'treatmentProfit'  => $treatmentProfit,
            'expenses'         => $expenses,
            'savings'          => $savings,
        ];
    }

    /**
     * Tính remaining theo công thức thống nhất:
     *   remaining = revenue - expenses
     * savings KHÔNG cộng vào remaining vì đây là khoản trích ra, không phải thu nhập.
     */
    private function calcRemaining(float $revenue, float $expenses): float
    {
        return $revenue - $expenses;
    }

    public function getDataToView() {
        $data = $this->getData();
        $totalRevenue = 0;
        $totalExpenses = 0;
        $totalMissingAmount = 0;

        foreach ($data as $id => &$row) {
            $petShopRevenue   = (float) ($row['petShopRevenue']   ?? 0);
            $spaRevenue       = (float) ($row['spaRevenue']       ?? 0);
            $treatmentRevenue = (float) ($row['treatmentRevenue'] ?? 0);
            $expenses         = (float) ($row['expenses']         ?? 0);
            $missingAmount    = (float) ($row['missingAmount']    ?? 0);

            $row['treatmentProfit'] = $treatmentRevenue * VetCare::TREATMENT_PROFIT_PERCENT;
            $row['revenue']         = $petShopRevenue + $spaRevenue + $treatmentRevenue;
            $row['remaining']       = $this->calcRemaining($row['revenue'], $expenses);

            $totalRevenue       += $row['revenue'];
            $totalExpenses      += $expenses;
            $totalMissingAmount += $missingAmount;
        }

        $totals = [
            'totalRevenue'       => $totalRevenue,
            'totalExpenses'      => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount,
        ];
        return [$totals, $data];
    }

    public function getDataToViewChart() {
        $data = $this->getData();
        $data = CommonService::sortData($data, 'date', 'asc');
        $totalRevenue       = 0;
        $totalExpenses      = 0;
        $totalMissingAmount = 0;

        $chartData = [];
        foreach ($data as $row) {
            $date            = $row['date'] ?? null;
            $dateToMicroTime = $date ? strtotime($date) * 1000 : 0;

            $petShopRevenue   = (float) ($row['petShopRevenue']   ?? 0);
            $petShopProfit    = (float) ($row['petShopProfit']    ?? 0);
            $spaRevenue       = (float) ($row['spaRevenue']       ?? 0);
            $treatmentRevenue = (float) ($row['treatmentRevenue'] ?? 0);
            $expenses         = (float) ($row['expenses']         ?? 0);
            $savings          = (float) ($row['savings']          ?? 0);
            $missingAmount    = (float) ($row['missingAmount']    ?? 0);

            $revenue   = $petShopRevenue + $spaRevenue + $treatmentRevenue;
            // Công thức thống nhất với getDataToView: remaining = revenue - expenses
            $remaining = $this->calcRemaining($revenue, $expenses);

            $totalRevenue       += $revenue;
            $totalExpenses      += $expenses;
            $totalMissingAmount += $missingAmount;

            $chartData['revenue'][]         = [$dateToMicroTime, (int) $revenue];
            $chartData['petShopRevenue'][]   = [$dateToMicroTime, (int) $petShopRevenue];
            $chartData['petShopProfit'][]    = [$dateToMicroTime, (int) $petShopProfit];
            $chartData['spaRevenue'][]       = [$dateToMicroTime, (int) $spaRevenue];
            $chartData['treatmentRevenue'][] = [$dateToMicroTime, (int) $treatmentRevenue];
            $chartData['expenses'][]         = [$dateToMicroTime, (int) $expenses];
            $chartData['savings'][]          = [$dateToMicroTime, (int) $savings];
            $chartData['remaining'][]        = [$dateToMicroTime, (int) $remaining];
        }

        $totals = [
            'totalRevenue'       => $totalRevenue,
            'totalExpenses'      => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount,
        ];
        return [$totals, $chartData];
    }
}
