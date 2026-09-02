<?php

declare(strict_types=1);

namespace Application;

use Application\Service\BackupService;
use Laminas\Mvc\MvcEvent;

class Module
{
    public function getConfig(): array
    {
        /** @var array $config */
        $config = include __DIR__ . '/../config/module.config.php';
        return $config;
    }

    /**
     * Đăng ký event listener sau khi app bootstrap xong.
     * Dùng EVENT_DISPATCH (không phải BOOTSTRAP) để container đã sẵn sàng.
     */
    public function onBootstrap(MvcEvent $event): void
    {
        $eventManager = $event->getApplication()->getEventManager();
        $eventManager->attach(
            MvcEvent::EVENT_DISPATCH,
            [$this, 'triggerDailyBackup'],
            // Priority thấp — chạy sau tất cả route/controller dispatch
            -1000
        );
    }

    /**
     * Trigger daily backup lần đầu mỗi ngày.
     * Chạy bất đồng bộ sau khi response đã được gửi (shutdown function)
     * để không ảnh hưởng performance của request.
     */
    public function triggerDailyBackup(MvcEvent $event): void
    {
        // Chỉ chạy với HTTP request thông thường, bỏ qua CLI/console
        $request = $event->getRequest();
        if (!$request instanceof \Laminas\Http\Request) {
            return;
        }

        // Chạy backup sau khi response gửi xong — không block UX
        register_shutdown_function(function () use ($event): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            try {
                $container     = $event->getApplication()->getServiceManager();
                $backupService = $container->get(BackupService::class);
                $backupService->backupDaily();
            } catch (\Throwable $e) {
                // Không làm crash app nếu backup lỗi
            }
        });
    }
}
