<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ExpensesRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExpensesController extends AbstractActionController
{
    private ExpensesRepository $expensesRepo;

    public function __construct(ExpensesRepository $expensesRepo)
    {
        $this->expensesRepo = $expensesRepo;
    }

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
            $postData = $this->getRequest()->getPost()->toArray();
            $this->expensesRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $date         = $this->params()->fromRoute('date', '');
        $expensesList = $this->expensesRepo->getDataByDate($date);
        return new ViewModel(['date' => $date, 'expensesList' => $expensesList]);
    }

    public function doEditAction()
    {
        try {
            $postData    = $this->getRequest()->getPost()->toArray();
            $this->expensesRepo->doEdit($postData);
            $redirectUrl = $this->url()->fromRoute('expenses');
            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!', 'redirectUrl' => $redirectUrl]);
        } catch (\Throwable $e) {
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
            $this->expensesRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->expensesRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
