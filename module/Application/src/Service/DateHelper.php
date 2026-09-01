<?php

declare(strict_types=1);

namespace Application\Service;

use Collator;

/**
 * Tiện ích so sánh ngày và chuỗi.
 * Tách ra từ CommonService để tuân thủ SRP.
 */
class DateHelper
{
    /**
     * So sánh hai chuỗi ngày định dạng dd-mm-yyyy.
     * Trả về -1, 0, hoặc 1.
     */
    public static function compareDate(string $date1, string $date2): int
    {
        $dt1 = \DateTime::createFromFormat('d-m-Y', $date1);
        $dt2 = \DateTime::createFromFormat('d-m-Y', $date2);

        // Fallback nếu format không khớp — thử parse tự do
        if (!$dt1) $dt1 = new \DateTime($date1);
        if (!$dt2) $dt2 = new \DateTime($date2);

        return $dt1->getTimestamp() <=> $dt2->getTimestamp();
    }

    /**
     * So sánh hai chuỗi văn bản với collation tiếng Việt.
     */
    public static function compareString(string $str1, string $str2): int
    {
        static $collator = null;
        if ($collator === null) {
            $collator = new Collator('vi_VN');
        }
        return $collator->compare($str1, $str2);
    }
}
