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
}
