<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ProductRepository;
use Application\Repository\CategoryRepository;
use Application\Repository\RepackageHistoryRepository;
use Application\Service\CommonService;
use Application\Service\CsrfService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ProductController extends AbstractActionController
{
    private ProductRepository         $productRepo;
    private RepackageHistoryRepository $historyRepo;
    private CategoryRepository         $categoryRepo;

    public function __construct(
        ProductRepository          $productRepo,
        RepackageHistoryRepository $historyRepo,
        CategoryRepository         $categoryRepo
    ) {
        $this->productRepo  = $productRepo;
        $this->historyRepo  = $historyRepo;
        $this->categoryRepo = $categoryRepo;
    }

    public function indexAction()
    {
        $categoryList = $this->categoryRepo->getNameList();
        return new ViewModel(['categoryList' => $categoryList]);
    }

    public function addAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->productRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm mới thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', null);
        if (is_null($id)) {
            return $this->redirect()->toRoute('product', ['action' => 'index']);
        }
        $productData = $this->productRepo->getDataById($id);
        return new ViewModel(['productData' => $productData]);
    }

    public function doEditAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->productRepo->doEdit($postData);
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
            $this->productRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            [$totals, $data] = $this->productRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel(array_merge($totals, $response));
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function repackageAction()
    {
        [$totals, $productList] = $this->productRepo->getDataToView();
        $repackageHistoryList   = $this->historyRepo->getDataToView(20);
        return new ViewModel(['productList' => $productList, 'repackageHistoryList' => $repackageHistoryList]);
    }

    public function doRepackageAction()
    {
        $request  = $this->getRequest();
        $postData = $request->getPost()->toArray();

        try {
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));
        } catch (\RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            return $this->redirect()->toUrl('/product/repackage');
        }

        try {
            $this->productRepo->doRepackage($postData, $this->historyRepo);
            $this->flashMessenger()->addSuccessMessage('Chiết hàng thành công.');
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
        }

        return $this->redirect()->toUrl('/product/repackage');
    }

    public function addInvoiceCheckAction()
    {
        [$totals, $productList] = $this->productRepo->getDataToView();
        CommonService::sortDataByVietnamese($productList, 'name');
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddInvoiceCheckAction()
    {
        $postData = $this->getRequest()->getPost()->toArray();
        $this->productRepo->doAddInvoiceCheck($postData);
        $this->flashMessenger()->addSuccessMessage('Cập nhật thành công.');
        return $this->redirect()->toUrl('/product/add-invoice-check');
    }
}
