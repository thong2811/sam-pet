<?php

namespace Application\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

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

    public static function logger($logFilePath = null) {
        if (is_null($logFilePath)) {
            $logFilePath = __DIR__ . '/../../../../logs/app_' . date('Y-m') . '.log';
        }
        $logger = new Logger("app");
        $logger->pushHandler(new StreamHandler($logFilePath));

        return $logger;
    }

    public static function loggerException() {
        $logFilePath = __DIR__ . '/../../../../logs/exception_' . date('Y-m') . '.log';
        $logger = new Logger("app");
        $logger->pushHandler(new StreamHandler($logFilePath));

        return $logger;
    }

    public static function executeCommand($command)
    {
        $output = [];
        $return_var = 0;
        $result = exec($command, $output, $return_var);
        if ($result === false) {
            $phpError = error_get_last();
            $msg = $phpError ? $phpError['message'] : 'No PHP error in this script';
            self::loggerException()->error($msg);
        }

        if ($return_var === 0) {
            return true;
        } else {
            self::loggerException()->error("Error executing script, return code: $return_var");
            return false;
        }
    }

    public static function backupDataToStocktaking() {
        $backupFileName = 'backup_data_stocktaking_' . time() . '.zip';
        $sourceFolder = realpath('./data');
        $backupFolder = "$sourceFolder/backup_stocktaking";

        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0777, true);
        }

        $backupFilePath = $backupFolder . DIRECTORY_SEPARATOR . $backupFileName;
        if (file_exists($backupFilePath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($backupFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            // Get list of files in source folder
            $files = scandir($sourceFolder);

            foreach ($files as $file) {
                // Skip . and .. directories
                if ($file !== '.' && $file !== '..') {
                    $filePath = $sourceFolder . DIRECTORY_SEPARATOR . $file;

                    // Check if it's a file and has .csv extension
                    if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
                        $zip->addFile($filePath, $file);
                    }
                }
            }

            $zip->close();
            return true;
        }

        return false;
    }
}
