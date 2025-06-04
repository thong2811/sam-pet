<?php

namespace Application\Model;

use Application\Library\LeagueCsv;
use Application\Service\CommonService;

class RepackageHistory extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header'   => ['id', 'date', 'content'],
        'fileName' => 'repackage_history.csv'
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

    public function getDataToView($limit = null) {
        $data = $this->getData();
        $data = CommonService::sortData($data, 'createdAt', 'desc');

        if (!is_null($limit)) {
            $data = array_slice($data, 0, $limit);
        }

        return $data;
    }
}
