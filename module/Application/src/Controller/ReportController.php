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
        return new ViewModel();
    }

    public function addAction()
    {
        $exportStockModel = new ExportStock();
        $exportStockTotalAmountByDate = $exportStockModel->totalAmountByDate();

        $vetCareModel = new VetCare();
        $vetCareTotalAmountByDate = $vetCareModel->totalAmountByDate();


        $expensesModel = new Expenses();
        $expensesTotalAmountByDate = $expensesModel->totalAmountByDate();

        return new ViewModel([
            "exportStockTotalAmountByDate" => $exportStockTotalAmountByDate,
            "vetCareTotalAmountByDate" => $vetCareTotalAmountByDate,
            "expensesTotalAmountByDate" => $expensesTotalAmountByDate
        ]);
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $report = new Report();
        $report->doAdd($postData);

        $this->redirect()->toRoute('report');
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', null);

        if (is_null($id)) {
            $this->redirect()->toRoute('report', ['action' => 'index']);
        }

        $reportModel = new Report();
        $reportData = $reportModel->getDataById($id);

        $exportStockModel = new ExportStock();
        $exportStockTotalAmountByDate = $exportStockModel->totalAmountByDate();

        $vetCareModel = new VetCare();
        $vetCareTotalAmountByDate = $vetCareModel->totalAmountByDate();

        $expensesModel = new Expenses();
        $expensesTotalAmountByDate = $expensesModel->totalAmountByDate();

        return new ViewModel([
            'reportData' => $reportData,
            "exportStockTotalAmountByDate" => $exportStockTotalAmountByDate,
            "vetCareTotalAmountByDate" => $vetCareTotalAmountByDate,
            "expensesTotalAmountByDate" => $expensesTotalAmountByDate
        ]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $report = new Report();
        $report->doEdit($postData);

        $this->redirect()->toRoute('report', ['action' => 'edit']);
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
            $report->deleteDataById($id);

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
