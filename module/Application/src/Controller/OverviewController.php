<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ReportRepository;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class OverviewController extends AbstractActionController
{
    private ReportRepository $reportRepo;

    public function __construct(ReportRepository $reportRepo)
    {
        $this->reportRepo = $reportRepo;
    }

    public function indexAction()
    {
        return $this->buildChartViewModel();
    }

    public function expensesAction()
    {
        return $this->buildChartViewModel();
    }

    private function buildChartViewModel(): ViewModel
    {
        [$totals, $data] = $this->reportRepo->getDataToViewChart();
        return new ViewModel(['data' => $data, 'totals' => $totals]);
    }
}
