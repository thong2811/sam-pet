<?php

declare(strict_types=1);

namespace Application\Service;

use Collator;

/**
 * Xử lý DataTables server-side processing.
 * Tách ra từ CommonService để tuân thủ SRP.
 */
class DataTableService
{
    /**
     * Pipeline: filter → sort → addNo → paginate → trả JSON cho DataTables.
     */
    public static function process($postData, array $data): array
    {
        $params = [
            'draw'           => isset($postData['draw'])               ? intval($postData['draw'])  : 0,
            'start'          => isset($postData['start'])              ? intval($postData['start']) : 0,
            'length'         => isset($postData['length'])             ? intval($postData['length']): 10,
            'searchValue'    => $postData['search']['value']           ?? '',
            'orderColumn'    => $postData['order'][0]['name']          ?? '',
            'orderDirection' => $postData['order'][0]['dir']           ?? 'asc',
        ];

        $filtered  = self::filter($data, $params['searchValue']);
        $sorted    = self::sort($filtered, $params['orderColumn'], $params['orderDirection']);
        $numbered  = self::addRowNumbers($sorted);
        $paginated = self::paginate($numbered, $params['start'], $params['length']);

        return [
            'draw'            => $params['draw'],
            'recordsTotal'    => count($data),
            'recordsFiltered' => count($filtered),
            'data'            => array_values($paginated),
        ];
    }

    public static function filter(array $data, string $searchValue): array
    {
        if ($searchValue === '') {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $row) {
            foreach ($row as $value) {
                if (stripos((string) $value, $searchValue) !== false) {
                    $result[$key] = $row;
                    break;
                }
            }
        }
        return $result;
    }

    public static function sort(array $data, string $column, string $direction): array
    {
        if ($column === '') {
            return $data;
        }

        uasort($data, function ($a, $b) use ($column, $direction) {
            $aVal = (string) ($a[$column] ?? '');
            $bVal = (string) ($b[$column] ?? '');

            if ($column === 'date') {
                $cmp = DateHelper::compareDate($aVal, $bVal);
            } elseif (is_numeric($aVal) && is_numeric($bVal)) {
                $cmp = (float) $aVal <=> (float) $bVal;
            } else {
                $cmp = DateHelper::compareString($aVal, $bVal);
            }

            return $cmp * ($direction === 'asc' ? 1 : -1);
        });

        return $data;
    }

    public static function paginate(array $data, int $start, int $length): array
    {
        return array_slice($data, $start, $length, true);
    }

    public static function addRowNumbers(array $data): array
    {
        $i = 1;
        foreach ($data as &$row) {
            $row['no'] = $i++;
        }
        return $data;
    }

    /**
     * Sort mảng associative theo key dùng collation tiếng Việt (in-place).
     */
    public static function sortByVietnamese(array &$data, string $key): void
    {
        $coll = new Collator('vi_VN');
        uasort($data, function ($a, $b) use ($key, $coll) {
            if (!isset($b[$key])) return 1;
            if (!isset($a[$key])) return -1;
            return $coll->compare((string) $a[$key], (string) $b[$key]);
        });
    }
}
