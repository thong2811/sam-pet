<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class Expenses extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'date', 'reason', 'amount', 'person', 'note'],
        'fileName' => 'expenses.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function doAdd($postData)
    {
        $dateList = $postData['date'] ?? [];
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
        foreach ($data as $row) {
            $date = $row['date'] ?? null;
            $amount = $row['amount'] ?? null;
            if (empty($date) || !is_numeric($amount)) {
                continue;
            }

            $sum = $total[$date] ?? 0;
            $total[$date] = $sum + $amount;
        }

        return $total;
    }

    public function getDataToView() {
        $data = $this->getData();

        foreach ($data as $id => &$row) {
            $sellingPrice = $row['sellingPrice'] ?? 0;
            $quantity = $row['quantity'] ?? 0;
            $row['total'] = (int) $sellingPrice * (int) $quantity;
            $row['action'] = sprintf('<button class="btn btn-danger" onclick="remove(\'%s\')"> Xóa </button>', $id);
        }

        return $data;
    }
}
