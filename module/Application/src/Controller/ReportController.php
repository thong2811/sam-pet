<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Expenses;
use Application\Model\ExportStock;
use Application\Model\Report;
use Application\Model\VetCare;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ReportController extends AbstractActionController
{
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
            "vetCareTotalAmountByDate" => $vetCareTotalAmountByDate,
            "expensesTotalAmountByDate" => $expensesTotalAmountByDate,
            "savingsTotalAmountByDate" => $savingsTotalAmountByDate
        ]);
    }

    public function doAddAction()
    {
        try {
            $request = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $report = new Report();
            $report->doAdd($postData);

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
            $request = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $report = new Report();
            $report->doEdit($postData);

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
            $body = $request->getContent();
            $data = json_decode($body, true);

            if (!isset($data['id'])) {
                return new JsonModel([
                    'success' => false,
                    'message' => 'ID không được cung cấp.',
                ]);
            }

            $id = $data['id'];
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

    public function dataTableServerSideAction()
    {
        try {
            $request = $this->getRequest();
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
}
