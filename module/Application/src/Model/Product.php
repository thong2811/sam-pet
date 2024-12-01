<?php

namespace Application\Model;

use Application\Service\CsvService;
use League\Csv\Exception;

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

    public function doEdit($postData) {

        $row = $this->mappingDataWithHeaders($postData);
        if (empty($row['id'])) {
            throw new Exception("Không thể cập nhật dữ liệu với ID rỗng !");
        }
        $this->updateRow($row['id'], $row);
    }
}
