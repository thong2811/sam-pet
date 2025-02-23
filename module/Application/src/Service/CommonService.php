<?php

namespace Application\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CommonService
{
    public static function getDataTablesParameters() {
        return [
            'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
            'start' => isset($_POST['start']) ? intval($_POST['start']) : 0,
            'length' => isset($_POST['length']) ? intval($_POST['length']) : 10,
            'searchValue' => isset($_POST['search']['value']) ? $_POST['search']['value'] : '',
            'orderColumnName' => isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0,
            'orderDirection' => isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc',
        ];
    }

    public static function filterData($data, $searchValue) {
        if (empty($searchValue)) {
            return $data;
        }

        $filteredData = [];
        foreach ($data as $key => $row) {
            foreach ($row as $value) {
                if (stripos($value, $searchValue) !== false) {
                    $filteredData[$key] = $row;
                    break;
                }
            }
        }
        return $filteredData;
    }

    public static function sortData($data, $orderColumn, $orderDirection) {

        uasort($data, function ($a, $b) use ($orderColumn, $orderDirection) {
            $aValue = $a[$orderColumn] ?? '';
            $bValue = $b[$orderColumn] ?? '';

            switch ($orderColumn) {
                case 'date':
                    $compare = self::compareDate($aValue, $bValue);
                    break;
                default:
                    if (is_numeric($aValue) && is_numeric($bValue)) {
                        $compare = (int) $aValue - (int) $bValue;
                        break;
                    }
                    $compare = self::compareString($aValue, $bValue);
            }

            return $compare * ($orderDirection === 'asc' ? 1 : -1);
        });

        return $data;
    }

    public static function paginateData($data, $start, $length) {
        return array_slice($data, $start, $length, true);
    }

    public static function addNoNumberToRowData($data) {
        $i = 1;
        foreach ($data as &$row) {
            $row['no'] = $i;
            $i++;
        }

        return $data;
    }

    public static function dataTableServerSideProcessing($postData, $data) {
        $params = [
            'draw' => isset($postData['draw']) ? intval($postData['draw']) : 0,
            'start' => isset($postData['start']) ? intval($postData['start']) : 0,
            'length' => isset($postData['length']) ? intval($postData['length']) : 10,
            'searchValue' => $postData['search']['value'] ?? '',
            'orderColumn' => $postData['order'][0]['name'] ?? '',
            'orderDirection' => $postData['order'][0]['dir'] ?? 'asc'
        ];

        $filteredData = self::filterData($data, $params['searchValue']);

        $sortedData = self::sortData($filteredData, $params['orderColumn'], $params['orderDirection']);
        $sortedData = self::addNoNumberToRowData($sortedData);

        $paginatedData = self::paginateData($sortedData, $params['start'], $params['length']);

        // Trả về JSON cho DataTables
        return [
            "draw" => $params['draw'],
            "recordsTotal" => count($data),
            "recordsFiltered" => count($filteredData),
            "data" => array_values($paginatedData),
        ];
    }

    public static function compareDate($date1, $date2) {
        $dt1 = new \DateTime($date1);
        $dt2 = new \DateTime($date2);

        if ($dt1->getTimestamp() === $dt2->getTimestamp()) {
            return 0;
        }
        return ($dt1->getTimestamp() < $dt2->getTimestamp() ? -1 : 1);
    }

    public static function compareString($str1, $str2) {
        $collator = new \Collator('vi_VN');
        return $collator->compare($str1, $str2);
    }

    public static function logger() {
        $logger = new Logger("app");
        $logFilePath = __DIR__ . '/../../../../logs/app_' . date('Y-m-d') . '.log';
        $logger->pushHandler(new StreamHandler($logFilePath));

        return $logger;
    }
}
