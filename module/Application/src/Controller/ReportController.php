<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Expenses;
use Application\Model\ExportStock;
use Application\Model\Report;
use Application\Model\VetCare;
use Application\Service\BackupService;
use Application\Service\CommonService;
use Application\Service\CsrfService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ReportController extends AbstractActionController
{
    private BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function indexAction()
    {
        $exportStockModel = new ExportStock();
        $exportStockTotalAmountByDate = $exportStockModel->totalAmountByDate();

        $vetCareModel = new VetCare();
        $vetCareTotalAmountByDate = $vetCareModel->totalAmountByDate();

        $expensesModel = new Expenses();
        list($expensesTotalAmountByDate, $savingsTotalAmountByDate) = $expensesModel->totalAmountByDate();

        return new ViewModel([
            "exportStockTotalAmountByDate" => $exportStockTotalAmountByDate,
            "vetCareTotalAmountByDate"     => $vetCareTotalAmountByDate,
            "expensesTotalAmountByDate"    => $expensesTotalAmountByDate,
            "savingsTotalAmountByDate"     => $savingsTotalAmountByDate,
        ]);
    }

    public function doAddAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();

            // Validate CSRF
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));

            $report = new Report();
            $report->doAdd($postData);

            $this->triggerBackupAfterResponse();

            return new JsonModel([
                'success' => true,
                'message' => 'Thêm mới thành công!',
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function doEditAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();

            // Validate CSRF
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));

            $report = new Report();
            $report->doEdit($postData);

            $this->triggerBackupAfterResponse();

            return new JsonModel([
                'success' => true,
                'message' => 'Cập nhật thành công!',
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function doDeleteAction()
    {
        try {
            $request = $this->getRequest();
            $body    = $request->getContent();
            $data    = json_decode($body, true);

            if (!isset($data['id'])) {
                return new JsonModel([
                    'success' => false,
                    'message' => 'ID không được cung cấp.',
                ]);
            }

            $id     = $data['id'];
            $report = new Report();
            $report->deleteRow($id);

            return new JsonModel([
                'success' => true,
                'message' => 'Xóa thành công!',
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /report/data-by-date?date=dd-mm-yyyy
     * Trả JSON tổng hợp dữ liệu theo ngày để auto-fill form báo cáo.
     */
    public function dataByDateAction()
    {
        try {
            $date = trim((string) $this->params()->fromQuery('date', ''));

            if (empty($date)) {
                return new JsonModel(['success' => false, 'message' => 'Thiếu tham số date.']);
            }

            $reportModel = new Report();
            $data        = $reportModel->getDataByDate($date);

            return new JsonModel(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel(['success' => false, 'message' => 'Lỗi server.']);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost();

            $reportModel = new Report();
            list($totals, $data) = $reportModel->getDataToView();

            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel(array_merge($totals, $response));
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Đăng ký callback chạy backup SAU KHI response đã được gửi về client.
     * Dùng register_shutdown_function để đảm bảo chạy sau toàn bộ output.
     * ignore_user_abort(true) giữ PHP tiếp tục dù browser đã đóng kết nối.
     */
    private function triggerBackupAfterResponse(): void
    {
        $backupService = $this->backupService;

        ignore_user_abort(true);

        register_shutdown_function(function () use ($backupService): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $backupService->backup();
        });
    }
}
