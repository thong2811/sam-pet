<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Expenses;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExpensesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $expensesModel = new Expenses();
            $expensesModel->doAdd($postData);

            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $date = $this->params()->fromRoute('date', '');

        $expensesModel = new Expenses();
        $expensesList = $expensesModel->getDataByKeyTypeDate('date', $date);

        return new ViewModel(['date' => $date, 'expensesList' => $expensesList]);
    }

    public function doEditAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();
            $date     = $postData['date'][0] ?? '';

            $expensesModel = new Expenses();
            $expensesModel->doEdit($postData);

            $redirectUrl = !empty($date)
                ? $this->url()->fromRoute('expenses', ['action' => 'edit', 'date' => $date])
                : $this->url()->fromRoute('expenses');

            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!', 'redirectUrl' => $redirectUrl]);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
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
            $expensesModel = new Expenses();
            $expensesModel->deleteRow($id);

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

            $expensesModel = new Expenses();
            $data = $expensesModel->getDataToView();

            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);

        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
