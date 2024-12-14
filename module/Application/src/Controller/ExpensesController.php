<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Expenses;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExpensesController extends AbstractActionController
{
    public function indexAction()
    {
        $expensesModel = new Expenses();
        $expensesList = $expensesModel->getData();

        return new ViewModel(['expensesList' => $expensesList]);
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $expensesModel = new Expenses();
        $expensesModel->doAdd($postData);

        $this->redirect()->toRoute('expenses');
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
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $expensesModel = new Expenses();
        $expensesModel->doEdit($postData);

        $this->redirect()->toRoute('expenses');
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
            $expensesModel->deleteDataById($id);

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
}
