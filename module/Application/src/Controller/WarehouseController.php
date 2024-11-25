<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class WarehouseController extends AbstractActionController
{
    public function indexAction() {
        $exportStockModel = new ExportStock();
        $data = $exportStockModel->getData();
        return new ViewModel(['data' => $data]);
    }
}
