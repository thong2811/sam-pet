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
            if ($a[$orderColumn] == $b[$orderColumn]) {
                return 0;
            }
            return ($a[$orderColumn] < $b[$orderColumn] ? -1 : 1) * ($orderDirection === 'asc' ? 1 : -1);
        });

        return $data;
    }

    public static function paginateData($data, $start, $length) {
        return array_slice($data, $start, $length, true);
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

        // Lấy danh sách cột từ dữ liệu
        $columns = array_keys(reset($data));

        // Lọc dữ liệu
        $filteredData = self::filterData($data, $params['searchValue']);

        // Sắp xếp dữ liệu
        $sortedData = self::sortData($filteredData, $columns, $params['orderColumnIndex'], $params['orderDirection']);

        // Phân trang dữ liệu
        $paginatedData = self::paginateData($sortedData, $params['start'], $params['length']);

        // Trả về JSON cho DataTables
        return [
            "draw" => $params['draw'],
            "recordsTotal" => count($data), // Tổng số dòng không lọc
            "recordsFiltered" => count($filteredData), // Tổng số dòng sau khi lọc
            "data" => array_values($paginatedData), // Chỉ lấy giá trị
        ];
    }

}
