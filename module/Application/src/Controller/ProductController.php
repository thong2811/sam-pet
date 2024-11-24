<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Product;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ProductController extends AbstractActionController
{
    public function indexAction()
    {
        $productModel = new Product();
        $data = $productModel->getData();
        return new ViewModel(['data' => $data]);
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $product = new Product();
        $product->addRow($postData);

        $this->redirect()->toRoute('product', ['action' => 'add']);
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', null);
        if (is_null($id)) {
            $this->redirect()->toRoute('product', ['action' => 'index']);
        }

        $productModel = new Product();
        $data = $productModel->getDataById($id);
        return new ViewModel(['data' => $data]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $product = new Product();
        $product->updateDataById($postData);

        $this->redirect()->toRoute('product', ['action' => 'edit']);
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
            $product->deleteDataById($id);
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