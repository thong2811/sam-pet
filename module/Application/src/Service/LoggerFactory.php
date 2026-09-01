<?php

declare(strict_types=1);

namespace Application\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Tạo Monolog Logger instances.
 * Tách ra từ CommonService để tuân thủ SRP.
 */
class LoggerFactory
{
    private static string $logDir = '';

    private static function logDir(): string
    {
        if (self::$logDir === '') {
            self::$logDir = __DIR__ . '/../../../../logs';
        }
        return self::$logDir;
    }

    /**
     * Logger ứng dụng chung — ghi vào logs/app_YYYY-MM.log
     */
    public static function app(?string $logFilePath = null): Logger
    {
        $path   = $logFilePath ?? (self::logDir() . '/app_' . date('Y-m') . '.log');
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler($path));
        return $logger;
    }

    /**
     * Logger exception riêng — ghi vào logs/exception_YYYY-MM.log
     */
    public static function exception(): Logger
    {
        $path   = self::logDir() . '/exception_' . date('Y-m') . '.log';
        $logger = new Logger('exception');
        $logger->pushHandler(new StreamHandler($path));
        return $logger;
    }
}
