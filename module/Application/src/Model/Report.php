<?php

namespace Application\Model;

use Application\Service\CsvService;

class Report extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'revenue', 'expenses', 'note'],
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
