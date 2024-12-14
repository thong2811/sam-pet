<?php

namespace Application\Service;

class CommonService
{
    public static function getDataTablesParameters() {
        return [
            'draw' => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
            'start' => isset($_POST['start']) ? intval($_POST['start']) : 0,
            'length' => isset($_POST['length']) ? intval($_POST['length']) : 10,
            'searchValue' => isset($_POST['search']['value']) ? $_POST['search']['value'] : '',
            'orderColumnIndex' => isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0,
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

    public static function sortData($data, $columns, $orderColumnIndex, $orderDirection) {
        $orderColumn = $columns[$orderColumnIndex] ?? $columns[0];

        uasort($data, function ($a, $b) use ($orderColumn, $orderDirection) {
            switch ($orderColumn) {
                case 'date':
                    return self::compareDate($a[$orderColumn], $b[$orderColumn]) * ($orderDirection === 'asc' ? 1 : -1);
                default:
                    if ($a[$orderColumn] == $b[$orderColumn]) {
                        return 0;
                    }
                    return ($a[$orderColumn] < $b[$orderColumn] ? -1 : 1) * ($orderDirection === 'asc' ? 1 : -1);
            }
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
            'orderColumnIndex' => isset($postData['order'][0]['column']) ? intval($postData['order'][0]['column']) : 0,
            'orderDirection' => $postData['order'][0]['dir'] ?? 'asc'
        ];

        $columns = array_keys(reset($data));

        $filteredData = self::filterData($data, $params['searchValue']);

        $sortedData = self::sortData($filteredData, $columns, $params['orderColumnIndex'], $params['orderDirection']);

        $paginatedData = self::paginateData($sortedData, $params['start'], $params['length']);

        $finalData = self::addNoNumberToRowData($paginatedData);

        // Trả về JSON cho DataTables
        return [
            "draw" => $params['draw'],
            "recordsTotal" => count($data),
            "recordsFiltered" => count($filteredData),
            "data" => array_values($finalData),
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
}
