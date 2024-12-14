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
}
