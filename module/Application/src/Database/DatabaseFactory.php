<?php

declare(strict_types=1);

namespace Application\Database;

use Psr\Container\ContainerInterface;

/**
 * Factory đăng ký Database vào Laminas Service Manager.
 *
 * dbPath và migrationsDir được tính tương đối từ getcwd()
 * (thư mục gốc của project khi chạy qua web server).
 * Có thể override qua config 'database' nếu cần.
 */
class DatabaseFactory
{
    public function __invoke(ContainerInterface $container): Database
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $dbConfig = $config['database'] ?? [];

        $dbPath        = $dbConfig['path']           ?? (getcwd() . '/data/app.db');
        $migrationsDir = $dbConfig['migrations_dir'] ?? (getcwd() . '/data/migrations');

        return new Database($dbPath, $migrationsDir);
    }
}
