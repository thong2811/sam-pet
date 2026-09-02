<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ImportStockRepository;
use Application\Repository\ProductRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ImportStockController extends AbstractActionController
{
    private ImportStockRepository $importRepo;
    private ProductRepository     $productRepo;

    public function __construct(ImportStockRepository $importRepo, ProductRepository $productRepo)
    {
        $this->importRepo  = $importRepo;
        $this->productRepo = $productRepo;
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $productList = $this->productRepo->getData();
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddAction()
    {
        try {
            $postData        = $this->getRequest()->getPost()->toArray();
            $productNameList = $this->productRepo->getProductNameList();
            $this->importRepo->doAdd($postData, $productNameList);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $date            = $this->params()->fromRoute('date', '');
        $importStockList = $this->importRepo->getDataByDate($date);
        $productList     = $this->productRepo->getData();
        return new ViewModel(['date' => $date, 'importStockList' => $importStockList, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        try {
            $postData        = $this->getRequest()->getPost()->toArray();
            $productNameList = $this->productRepo->getProductNameList();
            $this->importRepo->doEdit($postData, $productNameList);

            $date        = $postData['date'][0] ?? '';
            $redirectUrl = !empty($date)
                ? $this->url()->fromRoute('importStock', ['action' => 'edit', 'date' => $date])
                : $this->url()->fromRoute('importStock');

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
            $this->importRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->importRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
