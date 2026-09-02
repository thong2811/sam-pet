<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ReportRepository;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
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
        return new ViewModel();
    }

    public function expensesAction()
    {
        return new ViewModel();
    }

    /**
     * GET /overview/chart-data?from=dd-mm-yyyy&to=dd-mm-yyyy
     * Trả về JSON chart data cho Highcharts (lazy load).
     */
    public function chartDataAction()
    {
        try {
            $from = trim((string) $this->params()->fromQuery('from', ''));
            $to   = trim((string) $this->params()->fromQuery('to',   ''));

            [$totals, $chartData] = $this->reportRepo->getDataToViewChart($from, $to);

            return new JsonModel([
                'success' => true,
                'data'    => $chartData,
                'totals'  => $totals,
            ]);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
