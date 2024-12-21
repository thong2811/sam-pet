<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class VetCare extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'treatmentAmount', 'spaAmount', 'note'],
        'fileName' => 'vet-care.csv'
    ];

    public const TREATMENT_PROFIT_PERCENT = 0.4;

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
            if (empty($date) || !is_numeric($treatmentAmount) || !is_numeric($spaAmount)) {
                continue;
            }

            $treatmentSum = $total[$date]['treatment'] ?? 0;
            $total[$date]['treatment'] = $treatmentSum + $treatmentAmount;

            $spaSum = $total[$date]['spa'] ?? 0;
            $total[$date]['spa'] = $spaSum + $spaAmount;
        }

        return $total;
    }

    public function getDataToView() {
        $data = $this->getData();

        foreach ($data as $id => &$row) {
            $treatmentAmount = $row['treatmentAmount'] ?? 0;
            $spaAmount = $row['spaAmount'] ?? 0;
            $row['total'] = (int) $treatmentAmount + (int) $spaAmount;
            $row['action'] = sprintf('
                <button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>
                <a href="/vet-care/edit/%s" class="btn btn-primary">Chỉnh sửa</a>
                ', $id, $id);

        }

        return $data;
    }
}
