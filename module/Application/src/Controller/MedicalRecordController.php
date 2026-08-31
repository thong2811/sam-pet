<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\MedicalRecord;
use Application\Model\OwnerPet;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class MedicalRecordController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $petId = $this->params()->fromRoute('petId', '');

        $ownerPetModel = new OwnerPet();
        $petData = $ownerPetModel->getDataById($petId);

        $medicalModel = new MedicalRecord();
        $history = $petId ? $medicalModel->getHistoryByPetId($petId) : [];

        return new ViewModel([
            'petId' => $petId,
            'petData' => $petData,
            'history' => $history,
            'petList' => $ownerPetModel->getData(),
        ]);
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $model = new MedicalRecord();
        $model->doAdd($postData);

        $this->flashMessenger()->addSuccessMessage('Thêm lần khám thành công');
        $petId = $postData['pet_id'] ?? '';
        return $this->redirect()->toUrl('/medical-record/history/' . $petId);
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', '');

        $model = new MedicalRecord();
        $recordData = $model->getDataById($id);

        $ownerPetModel = new OwnerPet();
        $petData = $ownerPetModel->getDataById($recordData['pet_id'] ?? '');

        return new ViewModel([
            'recordData' => $recordData,
            'petData' => $petData,
        ]);
    }

    public function doEditAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $model = new MedicalRecord();
        $model->doEdit($postData);

        $this->flashMessenger()->addSuccessMessage('Cập nhật thành công');
        $petId = $postData['pet_id'] ?? '';
        return $this->redirect()->toUrl('/medical-record/history/' . $petId);
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

            $model = new MedicalRecord();
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

    public function historyAction()
    {
        $petId = $this->params()->fromRoute('petId', '');

        $ownerPetModel = new OwnerPet();
        $petData = $ownerPetModel->getDataById($petId);

        $medicalModel = new MedicalRecord();
        $history = $medicalModel->getHistoryByPetId($petId);

        // Sắp xếp theo ngày khám mới nhất
        uasort($history, function ($a, $b) {
            return CommonService::compareDate($b['visit_date'] ?? '', $a['visit_date'] ?? '');
        });

        return new ViewModel([
            'petId' => $petId,
            'petData' => $petData,
            'history' => $history,
        ]);
    }

    public function dataTableServerSideAction()
    {
        try {
            $request = $this->getRequest();
            $postData = $request->getPost();

            $model = new MedicalRecord();
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
}
