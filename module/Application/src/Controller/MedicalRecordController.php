<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\MedicalRecordRepository;
use Application\Repository\OwnerPetRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class MedicalRecordController extends AbstractActionController
{
    private MedicalRecordRepository $medicalRepo;
    private OwnerPetRepository      $ownerPetRepo;

    public function __construct(MedicalRecordRepository $medicalRepo, OwnerPetRepository $ownerPetRepo)
    {
        $this->medicalRepo  = $medicalRepo;
        $this->ownerPetRepo = $ownerPetRepo;
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $petId   = $this->params()->fromRoute('petId', '');
        $petData = $this->ownerPetRepo->getDataById($petId);
        $history = $petId ? $this->medicalRepo->getHistoryByPetId($petId) : [];
        return new ViewModel([
            'petId'   => $petId,
            'petData' => $petData,
            'history' => $history,
            'petList' => $this->ownerPetRepo->getData(),
        ]);
    }

    public function doAddAction()
    {
        try {
            $postData    = $this->getRequest()->getPost()->toArray();
            $this->medicalRepo->doAdd($postData);
            $petId       = $postData['pet_id'] ?? '';
            $redirectUrl = '/medical-record/history/' . $petId;
            return new JsonModel(['success' => true, 'message' => 'Thêm lần khám thành công!', 'redirectUrl' => $redirectUrl]);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $id         = $this->params()->fromRoute('id', '');
        $recordData = $this->medicalRepo->getDataById($id);
        $petData    = $this->ownerPetRepo->getDataById($recordData['pet_id'] ?? '');
        return new ViewModel(['recordData' => $recordData, 'petData' => $petData]);
    }

    public function doEditAction()
    {
        try {
            $postData    = $this->getRequest()->getPost()->toArray();
            $this->medicalRepo->doEdit($postData);
            $petId       = $postData['pet_id'] ?? '';
            $redirectUrl = '/medical-record/history/' . $petId;
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
            $this->medicalRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function historyAction()
    {
        $petId   = $this->params()->fromRoute('petId', '');
        $petData = $this->ownerPetRepo->getDataById($petId);
        // getHistoryByPetId đã sort DESC theo visit_date bằng SQL
        $history = $this->medicalRepo->getHistoryByPetId($petId);
        return new ViewModel(['petId' => $petId, 'petData' => $petData, 'history' => $history]);
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->medicalRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
