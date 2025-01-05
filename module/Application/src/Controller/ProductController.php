<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Product;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ProductController extends AbstractActionController
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
            $request = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $product = new Product();
            $product->doAdd($postData);

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

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', null);

        if (is_null($id)) {
            $this->redirect()->toRoute('product', ['action' => 'index']);
        }

        $productModel = new Product();
        $productData = $productModel->getDataById($id);

        return new ViewModel(['productData' => $productData]);
    }

    public function doEditAction()
    {
        try {
            $request = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $product = new Product();
            $product->doEdit($postData);

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
            $product = new Product();
            $product->deleteRow($id);

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

            $productModel = new Product();
            list($totals, $data) = $productModel->getDataToView();

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
