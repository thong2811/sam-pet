<?php

namespace Application\Model;

use Application\Library\LeagueCsv;
use Application\Service\CommonService;

class Report extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'petShopRevenue', 'petShopProfit', 'spaRevenue', 'treatmentRevenue', 'expenses', 'missingAmount', 'note'],
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

    public function getDataToView() {
        $data = $this->getData();
        $totalRevenue = 0;
        $totalExpenses = 0;
        $totalMissingAmount = 0;

        foreach ($data as $id => &$row) {
            $petShopRevenue = !empty($row['petShopRevenue']) ? $row['petShopRevenue'] : 0;
            $spaRevenue = !empty($row['spaRevenue']) ? $row['spaRevenue'] : 0;
            $treatmentRevenue = !empty($row['treatmentRevenue']) ? $row['treatmentRevenue'] : 0;
            $expenses = !empty($row['expenses']) ? $row['expenses'] : 0;
            $missingAmount = !empty($row['missingAmount']) ? $row['missingAmount'] : 0;

            $row['treatmentProfit'] = (int) $treatmentRevenue * VetCare::TREATMENT_PROFIT_PERCENT;
            $row['revenue'] = (int) $petShopRevenue + (int) $spaRevenue + (int) $treatmentRevenue;
            $row['remaining'] = $row['revenue'] - (int) $expenses;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);

            $totalRevenue += $row['revenue'];
            $totalExpenses += (int) $expenses;
            $totalMissingAmount += (int) $missingAmount;
        }

        $totals = [
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount
        ];
        return [ $totals, $data];
    }

    public function getDataToViewChart() {
        $data = $this->getData();
        $data = CommonService::sortData($data, 'date', 'asc');
        $totalRevenue = 0;
        $totalExpenses = 0;
        $totalMissingAmount = 0;

        $chartData = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? null;
            $dateToMicroTime = is_null($date) ? 0 : strtotime($date) * 1000;
            $petShopRevenue = !empty($row['petShopRevenue']) ? $row['petShopRevenue'] : 0;
            $petShopProfit = !empty($row['petShopProfit']) ? $row['petShopProfit'] : 0;

            $spaRevenue = !empty($row['spaRevenue']) ? $row['spaRevenue'] : 0;
            $treatmentRevenue = !empty($row['treatmentRevenue']) ? $row['treatmentRevenue'] : 0;

            $missingAmount = !empty($row['missingAmount']) ? $row['missingAmount'] : 0;

            $revenue = (int) $petShopRevenue + (int) $spaRevenue + (int) $treatmentRevenue;


            $totalRevenue += $revenue;
            $totalMissingAmount += (int) $missingAmount;

            $chartData['revenue'][] = [$dateToMicroTime, (int) $revenue];
            $chartData['petShopRevenue'][] = [$dateToMicroTime, (int) $petShopRevenue];
            $chartData['petShopProfit'][] = [$dateToMicroTime, (int) $petShopProfit];
            $chartData['spaRevenue'][] = [$dateToMicroTime, (int) $spaRevenue];
            $chartData['treatmentRevenue'][] = [$dateToMicroTime, (int) $treatmentRevenue];
        }

        $totals = [
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'totalMissingAmount' => $totalMissingAmount
        ];
        return [$totals, $chartData];
    }
}
