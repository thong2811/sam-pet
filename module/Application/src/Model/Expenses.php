<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Expenses extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'type', 'reason', 'amount', 'person', 'note'],
        'fileName' => 'expenses.csv'
    ];

    public const TYPE_OTHER = '0';
    public const TYPE_SAVINGS = '1';

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function doAdd($postData)
    {
        $dateList = $postData['date'] ?? [];
        $typeList = $postData['type'] ?? [];
        $reasonList = $postData['reason'] ?? [];
        $amountList = $postData['amount'] ?? [];
        $personList = $postData['person'] ?? [];
        $noteList = $postData['note'] ?? [];

        $rows = [];
        foreach ($dateList as $index => $date) {
            if (empty($date)) {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'type' => $typeList[$index] ?? self::TYPE_OTHER,
                'reason' => $reasonList[$index] ?? '',
                'amount' => $amountList[$index] ?? 0,
                'person' => $personList[$index] ?? '',
                'note' => $noteList[$index] ?? '',
            ];
        }

        if (count($rows)) {
            $this->addRows($rows);
        }
    }

    public function doEdit($postData)
    {
        $date        = $postData['date'][0]        ?? ($postData['dateKey'] ?? '');
        $typeList    = $postData['type']           ?? [];
        $reasonList  = $postData['reason']         ?? [];
        $amountList  = $postData['amount']         ?? [];
        $personList  = $postData['person']         ?? [];
        $noteList    = $postData['note']           ?? [];
        $dateList    = $postData['date']           ?? [];

        // Lấy ngày từ row đầu tiên để xác định scope xóa
        $targetDate = !empty($dateList[0]) ? $dateList[0] : '';

        // Replace-all: xóa toàn bộ rows của ngày đó, insert lại
        if (!empty($targetDate)) {
            $existingData   = $this->getData();
            $otherDaysData  = array_filter($existingData, function ($row) use ($targetDate) {
                return ($row['date'] ?? '') !== $targetDate;
            });

            $newRows = [];
            foreach ($dateList as $index => $rowDate) {
                if (empty($rowDate)) {
                    continue;
                }
                $now = time();
                $newRows[] = $this->mappingDataWithHeaders([
                    'id'        => self::generateId(),
                    'date'      => $rowDate,
                    'type'      => $typeList[$index]   ?? self::TYPE_OTHER,
                    'reason'    => $reasonList[$index]  ?? '',
                    'amount'    => $amountList[$index]  ?? 0,
                    'person'    => $personList[$index]  ?? '',
                    'note'      => $noteList[$index]    ?? '',
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            }

            $merged = array_values($otherDaysData);
            foreach ($newRows as $row) {
                $merged[$row['id']] = $row;
            }

            $this->saveData($merged);
        }
    }

    public function totalAmountByDate() {
        $data = $this->getData();

        $total = [];
        $totalSavings = [];
        foreach ($data as $row) {
            $date = $row['date'] ?? null;
            $amount = $row['amount'] ?? null;
            if (empty($date) || !is_numeric($amount)) {
                continue;
            }
            $sum = $total[$date] ?? 0;
            $total[$date] = $sum + $amount;

            $type = $row['type'] ?? '';
            if ($type === self::TYPE_SAVINGS) {
                $sumSavings = $totalSavings[$date] ?? 0;
                $totalSavings[$date] = $sumSavings + $amount;
            }
        }

        return [$total, $totalSavings];
    }

    public function getDataToView() {
        $data = $this->getData();

        foreach ($data as $id => &$row) {
            $type = $row['type'] ?? self::TYPE_OTHER;
            $row['typeText'] = $this->getTypeText($type);


            $sellingPrice = $row['sellingPrice'] ?? 0;
            $quantity = $row['quantity'] ?? 0;
            $row['total'] = (int) $sellingPrice * (int) $quantity;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }

        return $data;
    }

    public function getTypeText($type) {
        switch ($type) {
            case self::TYPE_SAVINGS:
                return 'Tiền tiết kiệm';
            default:
                return 'Khác';
        }
    }
}
