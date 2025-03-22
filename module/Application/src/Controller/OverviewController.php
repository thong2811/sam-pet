<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Report;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class OverviewController extends AbstractActionController
{
    public function indexAction()
    {
        $reportModel = new Report();
        list($totals, $data) = $reportModel->getDataToViewChart();

        return new ViewModel([
            'data' => $data,
        ]);
    }

    public function expensesAction()
    {
        $reportModel = new Report();
        list($totals, $data) = $reportModel->getDataToViewChart();

        return new ViewModel([
            'data' => $data,
        ]);
    }
}
