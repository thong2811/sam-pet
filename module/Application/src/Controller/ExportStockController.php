<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Application\Model\Product;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ExportStockController extends AbstractActionController
{
    public function indexAction()
    {
        $exportStockModel = new ExportStock();
        $data = $exportStockModel->getData();
        return new ViewModel(['data' => $data]);
    }

    public function addAction()
    {
        $productModel = new Product();
        $productList = $productModel->getData();
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

//        $product = new Product();
//        $product->addRow($postData);
        die('end');
//        $this->redirect()->toRoute('product', ['action' => 'add']);
    }
}
