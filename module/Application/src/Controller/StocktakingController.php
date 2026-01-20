<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Product;
use Application\Model\Stocktaking;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class StocktakingController extends AbstractActionController
{
    public function indexAction() {
        $productModel = new Product();
        list($totals, $productList) = $productModel->getDataToView();
        CommonService::sortDataByVietnamese($productList, 'name');
        $stocktakingModel = new Stocktaking();
        $stocktakingList = $stocktakingModel->getData();
        return new ViewModel(['productList' => $productList, 'stocktakingList' => $stocktakingList]);
    }

    public function doEditAction() {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();
        $stocktakingModel = new Stocktaking();
        $stocktakingModel->doEdit($postData);
        return $this->redirect()->toUrl('/stocktaking');
    }

    public function renewWarehouseAction() {
        $stocktakingModel = new Stocktaking();
        $stocktakingList = $stocktakingModel->getData();
        foreach ($stocktakingList as $stocktakingData) {
            if ($stocktakingData["stocktaking"] === "") {
                $this->flashMessenger()->addErrorMessage('Vẫn còn mặt hàng chưa kiểm kê. Hãy kiểm kê tất cả mặt hàng.');
                return $this->redirect()->toUrl('/stocktaking');
            }
        }

        $result = $stocktakingModel->renewWarehouse();
        if (!$result) {
            $this->flashMessenger()->addErrorMessage('Có lỗi xảy ra, thử lại sau.');
            return $this->redirect()->toUrl('/stocktaking');
        }

        $this->flashMessenger()->addSuccessMessage('Chốt kho thành công. Hãy kiểm tra lại kho hàng.');
        return $this->redirect()->toUrl('/product');
    }
}
