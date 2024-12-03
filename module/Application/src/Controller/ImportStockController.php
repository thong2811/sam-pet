<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ImportStock;
use Application\Model\Product;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ImportStockController extends AbstractActionController
{
    public function indexAction()
    {
        $importStockModel = new ImportStock();
        $importStockList = $importStockModel->getData();

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['importStockList' => $importStockList, 'productList' => $productList]);
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

        $importStockModel = new ImportStock();
        $importStockModel->doAdd($postData);

        $this->redirect()->toRoute('importStock');
    }

    public function editAction()
    {
        $date = $this->params()->fromRoute('date', '');

        $importStockModel = new ImportStock();
        $importStockList = $importStockModel->getDataByKey('date', $date);

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['date' => $date, 'importStockList' => $importStockList, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $importStockModel = new ImportStock();
        $importStockModel->doEdit($postData);

        $this->redirect()->toRoute('importStock');
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
            $importStockModel = new ImportStock();
            $importStockModel->deleteDataById($id);

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