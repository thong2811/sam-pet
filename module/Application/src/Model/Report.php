<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Report extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'petShopRevenue', 'petShopProfit', 'spaRevenue', 'treatmentRevenue', 'expenses', 'note'],
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

        foreach ($data as $id => &$row) {
            $petShopRevenue = !empty($row['petShopRevenue']) ? $row['petShopRevenue'] : 0;
            $spaRevenue = !empty($row['spaRevenue']) ? $row['spaRevenue'] : 0;
            $treatmentRevenue = !empty($row['treatmentRevenue']) ? $row['treatmentRevenue'] : 0;
            $expenses = !empty($row['expenses']) ? $row['expenses'] : 0;

            $row['treatmentProfit'] = (int) $treatmentRevenue * VetCare::TREATMENT_PROFIT_PERCENT;
            $row['revenue'] = (int) $petShopRevenue + (int) $spaRevenue + (int) $treatmentRevenue;
            $row['revenue'] = (int) $petShopRevenue + (int) $spaRevenue + (int) $treatmentRevenue;
            $row['remaining'] = $row['revenue'] - (int) $expenses;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }

        return $data;
    }
}
