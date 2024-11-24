<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Product;
use Application\Model\Warehouse;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class WarehouseController extends AbstractActionController
{
    public function indexAction()
    {
        $warehouseModel = new Warehouse();
        $data = $warehouseModel->getData();
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

        $warehouseModel = new Warehouse();
        $warehouseModel->addRow($postData);

        $this->redirect()->toRoute('warehouse', ['action' => 'add']);
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', null);
        if (is_null($id)) {
            $this->redirect()->toRoute('warehouse', ['action' => 'index']);
        }

        $warehouseModel = new Warehouse();
        $data = $warehouseModel->getDataById($id);
        return new ViewModel(['data' => $data]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $warehouseModel = new Warehouse();
        $warehouseModel->updateDataById($postData);

        $this->redirect()->toRoute('warehouse', ['action' => 'edit']);
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
            $warehouseModel = new Warehouse();
            $warehouseModel->deleteDataById($id);
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
