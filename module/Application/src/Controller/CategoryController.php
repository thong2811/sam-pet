<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\CategoryRepository;
use Application\Repository\ProductRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class CategoryController extends AbstractActionController
{
    private CategoryRepository $categoryRepo;
    private ProductRepository  $productRepo;

    public function __construct(CategoryRepository $categoryRepo, ProductRepository $productRepo)
    {
        $this->categoryRepo = $categoryRepo;
        $this->productRepo  = $productRepo;
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function doAddAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            if (empty(trim($postData['name'] ?? ''))) {
                return new JsonModel(['success' => false, 'message' => 'Tên danh mục không được rỗng.']);
            }
            $this->categoryRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm danh mục thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function doEditAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            if (empty(trim($postData['name'] ?? ''))) {
                return new JsonModel(['success' => false, 'message' => 'Tên danh mục không được rỗng.']);
            }
            $this->categoryRepo->doEdit($postData);
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
            $this->categoryRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->categoryRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
