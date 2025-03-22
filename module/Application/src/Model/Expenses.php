<?php

namespace Application\Model;

use Application\Library\LeagueCsv;
use phpDocumentor\Reflection\Type;

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
        $expensesIdList = $postData['expensesId'] ?? [];
        $dateList = $postData['date'] ?? [];
        $typeList = $postData['type'] ?? [];
        $reasonList = $postData['reason'] ?? [];
        $amountList = $postData['amount'] ?? [];
        $personList = $postData['person'] ?? [];
        $noteList = $postData['note'] ?? [];

        $rows = [];
        foreach ($expensesIdList as $index => $expensesId) {
            if (empty($expensesId)) {
                continue;
            }

            $rows[] = [
                'id' => $expensesId,
                'date' => $dateList[$index] ?? '',
                'type' => $typeList[$index] ?? self::TYPE_OTHER,
                'reason' => $reasonList[$index] ?? '',
                'amount' => $amountList[$index] ?? 0,
                'person' => $personList[$index] ?? '',
                'note' => $noteList[$index] ?? '',
            ];
        }

        if (count($rows)) {
            $this->updateRows($rows);
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
