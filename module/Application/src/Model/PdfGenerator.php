<?php

namespace Application\Model;

use FPDF;

class PdfGenerator
{
    public function generateSimplePdf(string $title, string $content): string
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);

        $pdf->Cell(0, 10, $title, 0, 1);

        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 8, $content);

        // Trả về PDF dưới dạng string
        return $pdf->Output('S');
    }
}
