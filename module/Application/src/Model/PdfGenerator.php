<?php

namespace Application\Model;

use FPDF;
use Mpdf\Mpdf;

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

    /**
     * @throws \Mpdf\MpdfException
     */
    public function generate(array $rows): string
    {
        $address = "số 105, Phan Văn Năm, Phường Cái Vồn, Tỉnh Vĩnh Long";
        $mst = ".....................................";
        $year = "2026";

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
            'tempDir' => '/var/www/html/data/mpdf',
//            'orientation' => 'L',
        ]);

        $html = '
        <style>
            body { font-family: DejaVu Sans; font-size: 13px; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-left { text-align: left; }
            h2 { margin: 15px 0 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #000; padding: 6px; }
            th { text-align: center; font-weight: bold; }
            .no-border { border: none; }
            .header {
                display: block;
                height: 50px;
            }
            .row {
                width: 100%;
                display: block;
            }
        </style>

        <div class="header">
            <div class="text-left" style="font-size:11px;width: 450px;float: left">
                <strong>HỘ KINH DOANH:</strong> NGUYỄN THỊ ANH THƯ<br>
                <strong>Địa chỉ:</strong> '. $address .' <br>
                <strong>Mã số thuế: '. $mst .'</strong> 
            </div>
            <div class="text-center" style="font-size:9px;width: 230px;float: right">
                <strong>Mẫu số S1a-HKD</strong><br>
                <i>(Ban hành kèm theo Thông tư số 152/2025/TT-BTC<br>
                ngày 31 tháng 12 năm 2025 của Bộ trưởng<br>Bộ Tài chính)</i>
            </div>
        </div>

        <div style="width: 600px;margin: 0 auto">
            <h2>SỔ CHI TIẾT DOANH THU BÁN HÀNG HÓA, DỊCH VỤ</h2>
            <div style="margin-left: 25px">
                Địa điểm kinh doanh: '. $address .'<br>
                Kỳ kê khai: Năm '. $year .'
            </div>
        </div>
        <div class="row text-right" style="padding-right: 10px">Đơn vị tính: Đồng</div>
        <table>
            <tr>
                <th width="10%">Ngày tháng</th>
                <th width="65%">Diễn giải</th>
                <th width="25%">Số tiền</th>
            </tr>
            <tr>
                <th>A</th>
                <th>B</th>
                <th>1</th>
            </tr>';

        $total = 0;

        foreach ($rows as $row) {
            $html .= '
            <tr>
                <td>'.$row['date'].'</td>
                <td>'.$row['desc'].'</td>
                <td class="right-text">'.number_format($row['total']).'</td>
            </tr>';
            $total += $row['total'];
        }

        $html .= '
            <tr>
                <td colspan="2" class="center"><strong>Tổng cộng</strong></td>
                <td class="right-text"><strong>'.number_format($total).'</strong></td>
            </tr>
        </table>

        <br><br>
        <div style="float: right; width: 250px">
            <div class="text-center">
                Ngày ... tháng ... năm ...<br>
                <strong>NGƯỜI ĐẠI DIỆN HỘ KINH DOANH</strong><br>
                <i>(Ký, họ tên, đóng dấu)</i>
            </div>
        </div>
        ';
//        echo $html;
//        die();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S'); // trả về string
    }
}
