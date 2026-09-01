<?php

declare(strict_types=1);

namespace Application\Service;

use Monolog\Logger;
use ZipArchive;

/**
 * CommonService — Facade giữ backward-compatibility.
 * Logic thực tế đã được chuyển sang:
 *   - DataTableService  (filter / sort / paginate)
 *   - LoggerFactory     (logger / loggerException)
 *   - DateHelper        (compareDate / compareString)
 *
 * backupDataToStocktaking() đã được chuyển sang BackupService::backupForStocktaking().
 * getDataTablesParameters() đã bị xóa (dead code đọc $_POST trực tiếp).
 */
class CommonService
{
    // -------------------------------------------------------------------------
    // DataTable — delegate sang DataTableService
    // -------------------------------------------------------------------------

    public static function filterData(array $data, string $searchValue): array
    {
        return DataTableService::filter($data, $searchValue);
    }

    public static function sortData(array $data, string $orderColumn, string $orderDirection): array
    {
        return DataTableService::sort($data, $orderColumn, $orderDirection);
    }

    public static function paginateData(array $data, int $start, int $length): array
    {
        return DataTableService::paginate($data, $start, $length);
    }

    public static function addNoNumberToRowData(array $data): array
    {
        return DataTableService::addRowNumbers($data);
    }

    public static function dataTableServerSideProcessing($postData, array $data): array
    {
        return DataTableService::process($postData, $data);
    }

    public static function sortDataByVietnamese(array &$data, string $key): void
    {
        DataTableService::sortByVietnamese($data, $key);
    }

    // -------------------------------------------------------------------------
    // Logger — delegate sang LoggerFactory
    // -------------------------------------------------------------------------

    public static function logger(?string $logFilePath = null): Logger
    {
        return LoggerFactory::app($logFilePath);
    }

    public static function loggerException(): Logger
    {
        return LoggerFactory::exception();
    }

    // -------------------------------------------------------------------------
    // Date / String — delegate sang DateHelper
    // -------------------------------------------------------------------------

    public static function compareDate(string $date1, string $date2): int
    {
        return DateHelper::compareDate($date1, $date2);
    }

    public static function compareString(string $str1, string $str2): int
    {
        return DateHelper::compareString($str1, $str2);
    }

    // -------------------------------------------------------------------------
    // Shell execution
    // -------------------------------------------------------------------------

    public static function executeCommand(string $command): bool
    {
        $output     = [];
        $returnVar  = 0;
        $result     = exec($command, $output, $returnVar);

        if ($result === false) {
            $phpError = error_get_last();
            $msg      = $phpError ? $phpError['message'] : 'No PHP error in this script';
            LoggerFactory::exception()->error($msg);
        }

        if ($returnVar === 0) {
            return true;
        }

        LoggerFactory::exception()->error("Error executing script, return code: $returnVar");
        return false;
    }

    // -------------------------------------------------------------------------
    // backupDataToStocktaking — delegate sang BackupService
    // Giữ lại để không break Stocktaking::renewWarehouse() trong lúc chuyển đổi.
    // -------------------------------------------------------------------------

    /**
     * @deprecated Dùng BackupService::backupForStocktaking() thay thế.
     */
    public static function backupDataToStocktaking(): bool
    {
        $backupService = new BackupService();
        return $backupService->backupForStocktaking();
    }
}
