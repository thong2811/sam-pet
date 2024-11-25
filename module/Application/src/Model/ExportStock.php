<?php

namespace Application\Model;

use Application\Service\CsvService;

class ExportStock extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'quantity', 'date'],
        'fileName' => 'export-stock.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }
}
