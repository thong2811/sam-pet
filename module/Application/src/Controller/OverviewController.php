<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Report;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class OverviewController extends AbstractActionController
{
    /**
     * Dashboard doanh thu — /overview (default) và /overview/index
     */
    public function indexAction()
    {
        return $this->buildChartViewModel();
    }

    /**
     * Dashboard thu/chi — /overview/expenses
     * Data giống indexAction, chỉ khác view template.
     */
    public function expensesAction()
    {
        return $this->buildChartViewModel();
    }

    private function buildChartViewModel(): ViewModel
    {
        $reportModel = new Report();
        [, $data] = $reportModel->getDataToViewChart();

        return new ViewModel(['data' => $data]);
    }
}
