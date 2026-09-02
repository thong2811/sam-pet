<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ProductRepository;
use Application\Repository\StocktakingRepository;
use Application\Service\CommonService;
use Application\Service\CsrfService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class StocktakingController extends AbstractActionController
{
    private ProductRepository     $productRepo;
    private StocktakingRepository $stocktakingRepo;

    public function __construct(ProductRepository $productRepo, StocktakingRepository $stocktakingRepo)
    {
        $this->productRepo      = $productRepo;
        $this->stocktakingRepo  = $stocktakingRepo;
    }

    public function indexAction()
    {
        [, $productList]  = $this->productRepo->getDataToView();
        CommonService::sortDataByVietnamese($productList, 'name');
        $stocktakingList  = $this->stocktakingRepo->getData();
        return new ViewModel(['productList' => $productList, 'stocktakingList' => $stocktakingList]);
    }

    public function doEditAction()
    {
        $postData = $this->getRequest()->getPost()->toArray();
        $this->stocktakingRepo->doEdit($postData);
        return $this->redirect()->toUrl('/stocktaking');
    }

    public function renewWarehouseAction()
    {
        $request = $this->getRequest();

        try {
            CsrfService::validateOrFail(CsrfService::getTokenFromRequest($request));
        } catch (\RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            return $this->redirect()->toUrl('/stocktaking');
        }

        if (!$request->isPost()) {
            return $this->redirect()->toUrl('/stocktaking');
        }

        $postData    = $request->getPost()->toArray();
        $closedAt    = $postData['closedAt'] ?? date('d-m-Y');
        $note        = $postData['note']     ?? '';

        try {
            $this->stocktakingRepo->renewWarehouse($closedAt, $note);
            $this->flashMessenger()->addSuccessMessage('Chốt kho thành công. Hãy kiểm tra lại kho hàng.');
            return $this->redirect()->toUrl('/product');
        } catch (\RuntimeException $e) {
            $this->flashMessenger()->addErrorMessage($e->getMessage());
            return $this->redirect()->toUrl('/stocktaking');
        }
    }
}
