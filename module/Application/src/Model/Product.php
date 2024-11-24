<?php

namespace Application\Model;

use Application\Service\CsvService;

class Product extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'name', 'unit', 'sellingPrice', 'purchasePrice' ],
        'fileName' => 'product.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }
}
