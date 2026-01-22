<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Application\Model\PdfGenerator;

class PdfController extends AbstractActionController
{
    private PdfGenerator $pdfGenerator;

    public function __construct()
    {
        $pdfGenerator = new PdfGenerator();
        $this->pdfGenerator = $pdfGenerator;
    }

    public function indexAction()
    {
        $data = [
            ['date' => '01/01/2025', 'desc' => 'Bán hàng A', 'amount' => 1500000],
            ['date' => '01/01/2025', 'desc' => 'Bán hàng A', 'amount' => 1500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
            ['date' => '02/01/2025', 'desc' => 'Dịch vụ B', 'amount' => 2500000],
        ];

        $pdf = $this->pdfGenerator->generate($data);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaders([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="so-doanh-thu.pdf"',
        ]);

        return $response->setContent($pdf);
    }
}
