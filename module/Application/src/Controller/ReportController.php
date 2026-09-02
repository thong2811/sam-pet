<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ExportStockRepository;
use Application\Repository\ExpensesRepository;
use Application\Repository\ReportRepository;
use Application\Repository\VetCareRepository;
use Application\Service\BackupService;
use Application\Service\CommonService;
use Application\Service\CsrfService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ReportController extends AbstractActionController
{
    private ReportRepository      $reportRepo;
    private ExportStockRepository $exportRepo;
    private VetCareRepository     $vetCareRepo;
    private ExpensesRepository    $expensesRepo;
    private BackupService         $backupService;

    public function __construct(
        ReportRepository      $reportRepo,
        ExportStockRepository $exportRepo,
        VetCareRepository     $vetCareRepo,
        ExpensesRepository    $expensesRepo,
        BackupService         $backupService
    ) {
        $this->reportRepo    = $reportRepo;
        $this->exportRepo    = $exportRepo;
        $this->vetCareRepo   = $vetCareRepo;
        $this->expensesRepo  = $expensesRepo;
        $this->backupService = $backupService;
    }

    public function indexAction()
    {
        $exportStockTotalAmountByDate          = $this->exportRepo->totalAmountByDate();
        $vetCareTotalAmountByDate              = $this->vetCareRepo->totalAmountByDate();
        [$expensesTotalAmountByDate, $savingsTotalAmountByDate] = $this->expensesRepo->totalAmountByDate();

        return new ViewModel([
            'exportStockTotalAmountByDate' => $exportStockTotalAmountByDate,
            'vetCareTotalAmountByDate'     => $vetCareTotalAmountByDate,
            'expensesTotalAmountByDate'    => $expensesTotalAmountByDate,
            'savingsTotalAmountByDate'     => $savingsTotalAmountByDate,
        ]);
    }

    public function doAddAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));
            $this->reportRepo->doAdd($postData);
            $this->triggerBackupAfterResponse();
            return new JsonModel(['success' => true, 'message' => 'Thêm mới thành công!']);
        } catch (\RuntimeException $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function doEditAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));
            $this->reportRepo->doEdit($postData);
            $this->triggerBackupAfterResponse();
            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!']);
        } catch (\RuntimeException $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function doDeleteAction()
    {
        try {
            $data = json_decode($this->getRequest()->getContent(), true);
            if (!isset($data['id'])) {
                return new JsonModel(['success' => false, 'message' => 'ID không được cung cấp.']);
            }
            $this->reportRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataByDateAction()
    {
        try {
            $date = trim((string) $this->params()->fromQuery('date', ''));
            if (empty($date)) {
                return new JsonModel(['success' => false, 'message' => 'Thiếu tham số date.']);
            }
            $data = $this->reportRepo->getDataByDate($date);
            return new JsonModel(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel(['success' => false, 'message' => 'Lỗi server.']);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            [$totals, $data] = $this->reportRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel(array_merge($totals, $response));
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

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
