<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\OwnerPetRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class OwnerPetController extends AbstractActionController
{
    private OwnerPetRepository $ownerPetRepo;

    public function __construct(OwnerPetRepository $ownerPetRepo)
    {
        $this->ownerPetRepo = $ownerPetRepo;
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
            $this->ownerPetRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function doEditAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->ownerPetRepo->doEdit($postData);
            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!']);
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
            $this->ownerPetRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->ownerPetRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function searchAction()
    {
        $query   = $this->getRequest()->getQuery('q', '');
        $results = $this->ownerPetRepo->searchByPetName($query);

        $data = [];
        foreach ($results as $row) {
            $data[] = [
                'id'   => $row['id'],
                'text' => $row['pet_name'] . ' - ' . $row['owner_name'] . ' (' . $row['phone'] . ')',
            ];
        }
        return new JsonModel(['results' => $data]);
    }
}
