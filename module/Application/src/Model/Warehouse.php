<?php

namespace Application\Model;

use Application\Service\CsvService;

class Warehouse extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'quantity', 'price', 'date_added'],
        'fileName' => 'warehouse.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }
}
