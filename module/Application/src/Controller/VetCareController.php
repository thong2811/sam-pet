<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Application\Model\VetCare;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class VetCareController extends AbstractActionController
{
    public function indexAction()
    {
        $vetCareModel = new VetCare();
        $vetCareList = $vetCareModel->getData();

        return new ViewModel(['vetCareList' => $vetCareList]);
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        $this->layout()->setTemplate(false);

        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $vetCareModel = new VetCare();
        $vetCareModel->addRow($postData);

        $this->flashMessenger()->addSuccessMessage('Thêm thành công');
        return $this->redirect()->toRoute('vetCare');
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', '');

        $vetCareModel = new VetCare();
        $vetCareData = $vetCareModel->getDataById($id);

        return new ViewModel(['vetCareData' => $vetCareData]);
    }

    public function doEditAction()
    {
        $this->layout()->setTemplate(false);

        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $vetCareModel = new VetCare();
        $vetCareModel->doEdit($postData);

        $this->flashMessenger()->addSuccessMessage('Cập nhật thành công');
        return $this->redirect()->toUrl($this->getRequest()->getHeader('Referer')->getUri());
    }

    public function doDeleteAction()
    {
        $this->layout()->setTemplate(false);

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
            $vetCareModel = new VetCare();
            $vetCareModel->deleteRow($id);

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
        $this->layout()->setTemplate(false);

        try {
            $request = $this->getRequest();
            $postData = $request->getPost();

            $vetCareModel = new VetCare();
            $data = $vetCareModel->getDataToView();

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
