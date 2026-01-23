<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class ExportInvoice extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'content', 'total'],
        'fileName' => 'export-invoice.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }
}
