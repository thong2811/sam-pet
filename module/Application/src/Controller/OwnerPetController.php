<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\OwnerPet;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class OwnerPetController extends AbstractActionController
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

            $model = new OwnerPet();
            $model->doAdd($postData);

            return new JsonModel([
                'success' => true,
                'message' => 'Thêm thành công!',
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

            $model = new OwnerPet();
            $model->doEdit($postData);

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

            $model = new OwnerPet();
            $model->deleteRow($data['id']);

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

            $model = new OwnerPet();
            $data = $model->getDataToView();

            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function searchAction()
    {
        $request = $this->getRequest();
        $query = $request->getQuery('q', '');

        $model = new OwnerPet();
        $results = $model->searchByPetName($query);

        $data = [];
        foreach ($results as $id => $row) {
            $data[] = [
                'id' => $id,
                'text' => $row['pet_name'] . ' - ' . $row['owner_name'] . ' (' . $row['phone'] . ')',
            ];
        }

        return new JsonModel(['results' => $data]);
    }
}
