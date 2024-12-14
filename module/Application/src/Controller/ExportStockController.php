<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Application\Model\Product;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExportStockController extends AbstractActionController
{
    public function indexAction()
    {
        $exportStockModel = new ExportStock();
        $exportStockList = $exportStockModel->getData();

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['exportStockList' => $exportStockList, 'productList' => $productList]);
    }

    public function addAction()
    {
        $productModel = new Product();
        $productList = $productModel->getData();
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $exportStockModel = new ExportStock();
        $exportStockModel->doAdd($postData);

        $this->redirect()->toRoute('exportStock');
    }

    public function editAction()
    {
        $date = $this->params()->fromRoute('date', '');

        $exportStockModel = new ExportStock();
        $exportStockList = $exportStockModel->getDataByKeyTypeDate('date', $date);

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['date' => $date, 'exportStockList' => $exportStockList, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $exportStockModel = new ExportStock();
        $exportStockModel->doEdit($postData);

        $this->redirect()->toRoute('exportStock');
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
            $exportStockModel = new ExportStock();
            $exportStockModel->deleteDataById($id);

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

            $exportStockModel = new ExportStock();
            $data = $exportStockModel->getDataToView();

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
