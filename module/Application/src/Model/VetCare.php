<?php

namespace Application\Model;

use Application\Service\CsvService;

class VetCare extends CsvService
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'treatmentAmount', 'spaAmount', 'note'],
        'fileName' => 'vet-care.csv'
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

    public function totalAmountByDate() {
        $data = $this->getData();

        $total = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? null;
            $treatmentAmount = $row['treatmentAmount'] ?? null;
            $spaAmount = $row['spaAmount'] ?? null;
            $quantity = $row['quantity'] ?? null;
            if (empty($date) || !is_numeric($treatmentAmount) || !is_numeric($spaAmount)) {
                continue;
            }

            $sum = $total[$date] ?? 0;
            $total[$date] = $sum + ($treatmentAmount + $spaAmount);
        }

        return $total;
    }
}
