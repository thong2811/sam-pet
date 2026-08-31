<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Service\BackupService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class SettingsController extends AbstractActionController
{
    private BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Trang quản lý cài đặt / backup-restore.
     */
    public function indexAction(): ViewModel
    {
        return new ViewModel();
    }

    /**
     * Tải backup.zip từ GitHub Releases về và giải nén vào /data.
     * Sau khi xong redirect về trang Settings với flash message.
     */
    public function doRestoreAction()
    {
        try {
            $this->backupService->restore();
            $this->flashMessenger()->addSuccessMessage('Khôi phục dữ liệu thành công!');
        } catch (\Throwable $e) {
            $this->flashMessenger()->addErrorMessage('Khôi phục thất bại: ' . $e->getMessage());
        }

        return $this->redirect()->toRoute('settings');
    }
}
