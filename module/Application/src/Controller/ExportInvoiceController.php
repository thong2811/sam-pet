<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Application\Model\Product;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ExportInvoiceController extends AbstractActionController
{
    public function indexAction() {

    }

    public function addAction()
    {
        $date = $this->params()->fromRoute('date', '');

        $exportStockModel = new ExportStock();
        $exportStockList = $exportStockModel->getDataByKeyTypeDate('date', $date);
        $exportProductList = $exportStockModel->mergeExportStockByItem($exportStockList);

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['date' => $date, 'exportProductList' => $exportProductList, 'productList' => $productList]);
    }
}
