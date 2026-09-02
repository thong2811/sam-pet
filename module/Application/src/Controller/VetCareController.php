<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\VetCareRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class VetCareController extends AbstractActionController
{
    private VetCareRepository $vetCareRepo;

    public function __construct(VetCareRepository $vetCareRepo)
    {
        $this->vetCareRepo = $vetCareRepo;
    }

    public function indexAction()
    {
        $vetCareList = $this->vetCareRepo->getData();
        return new ViewModel(['vetCareList' => $vetCareList]);
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->vetCareRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $id          = $this->params()->fromRoute('id', '');
        $vetCareData = $this->vetCareRepo->getDataById($id);
        return new ViewModel(['vetCareData' => $vetCareData]);
    }

    public function doEditAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->vetCareRepo->doEdit($postData);
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
            $this->vetCareRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->vetCareRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
