<?php

declare(strict_types=1);

namespace Application\Service;

class GoogleSheetsService
{
    public const DEFAULT_APPS_SCRIPT_URL = 'https://script.google.com/macros/s/AKfycbyE6Sms6dOVaqowarrIx8Jdj53PcvZzqua4bafuiXhu2W9eWrQW57Tmw1d7lsAneo4/exec';

    private string $appsScriptUrl;

    public function __construct(?string $appsScriptUrl = null)
    {
        $url = $appsScriptUrl
            ?? ($_ENV['GOOGLE_APPS_SCRIPT_URL'] ?? null)
            ?? getenv('GOOGLE_APPS_SCRIPT_URL')
            ?: self::DEFAULT_APPS_SCRIPT_URL;

        $this->appsScriptUrl = (string) $url;
    }

    public function getAppsScriptUrl(): string
    {
        return $this->appsScriptUrl;
    }

    /**
     * Lấy toàn bộ dữ liệu từ Google Sheets (tab PhieuXuat).
     * Apps Script luôn trả tất cả values dạng string — method này cast về đúng type PHP.
     *
     * @return array[]
     * @throws \RuntimeException nếu không kết nối được hoặc Apps Script trả lỗi
     */
    public function fetchAll(): array
    {
        $response = $this->httpGet($this->appsScriptUrl);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Phản hồi từ Google Sheets không hợp lệ: ' . json_last_error_msg()
            );
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        if ($status !== 'ok' && $status !== 'success') {
            $message = $data['message'] ?? 'Lỗi không xác định từ Apps Script.';
            throw new \RuntimeException('Google Sheets trả về lỗi: ' . $message);
        }

        return $this->castRows($data['rows'] ?? []);
    }

    /**
     * Lấy dữ liệu theo ngày cụ thể (filter phía PHP — Apps Script không hỗ trợ filter param).
     *
     * @param string $date Định dạng DD-MM-YYYY, ví dụ "27-12-2025"
     * @return array[]
     * @throws \RuntimeException
     */
    public function fetchByDate(string $date): array
    {
        $rows = $this->fetchAll();

        return array_values(array_filter($rows, function (array $row) use ($date): bool {
            return ($row['date'] ?? '') === $date;
        }));
    }

    /**
     * Lấy toàn bộ dữ liệu chiết hàng từ Google Sheets (tab repackage / repackage_history).
     * Endpoint: GET ?type=repackage
     *
     * @return array[]
     * @throws \RuntimeException
     */
    public function fetchRepackageAll(): array
    {
        $url = $this->appsScriptUrl . '?type=repackage';
        $response = $this->httpGet($url);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Phản hồi từ Google Sheets không hợp lệ: ' . json_last_error_msg()
            );
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        if ($status !== 'ok' && $status !== 'success') {
            $message = $data['message'] ?? 'Lỗi không xác định từ Apps Script.';
            throw new \RuntimeException('Google Sheets trả về lỗi: ' . $message);
        }

        return $this->castRepackageRows($data['repackageRows'] ?? []);
    }

    /**
     * Thực hiện HTTP GET request tới Google Apps Script (sử dụng cURL với IPv4 resolve, fallback file_get_contents).
     *
     * @param string $url
     * @return string
     * @throws \RuntimeException
     */
    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT      => 'SamPet/2.0 GoogleSheetsClient',
            ]);

            $response = curl_exec($ch);
            $error    = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && ($httpCode === 200 || $httpCode === 302)) {
                return (string) $response;
            }

            if ($response === false) {
                throw new \RuntimeException('Không thể kết nối Google Sheets (cURL): ' . ($error ?: "HTTP $httpCode"));
            }
        }

        // Fallback file_get_contents
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 30,
                'follow_location' => true,
                'max_redirects'   => 5,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException(
                'Không thể kết nối Google Sheets: ' . ($error['message'] ?? 'Lỗi không xác định.')
            );
        }

        return (string) $response;
    }

    /**
     * Lấy dữ liệu chiết hàng theo ngày.
     *
     * @param string $date Định dạng DD-MM-YYYY
     * @return array[]
     * @throws \RuntimeException
     */
    public function fetchRepackageByDate(string $date): array
    {
        $rows = $this->fetchRepackageAll();

        return array_values(array_filter($rows, function (array $row) use ($date): bool {
            return ($row['date'] ?? '') === $date;
        }));
    }

    /**
     * Cast dữ liệu chiết hàng từ Apps Script về đúng kiểu PHP.
     *
     * @param array[] $rows
     * @return array[]
     */
    private function castRepackageRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $cleanId = ltrim((string) ($row['id'] ?? ''), "'");
            $cleanSessionId = ltrim((string) ($row['sessionId'] ?? ''), "'");
            $cleanFromPid = ltrim((string) ($row['fromProductId'] ?? ''), "'");
            $cleanToPid = ltrim((string) ($row['toProductId'] ?? ''), "'");

            $fromQuantity = is_numeric($row['fromQuantity'] ?? null) ? (float) $row['fromQuantity'] : 0.0;
            $sessionFromQty = isset($row['sessionFromQty']) && is_numeric($row['sessionFromQty'])
                ? (float) $row['sessionFromQty']
                : $fromQuantity;

            return [
                'id'              => $cleanId,
                'sessionId'       => $cleanSessionId,
                'date'            => (string) ($row['date'] ?? ''),
                'fromProductId'   => $cleanFromPid,
                'fromProductName' => (string) ($row['fromProductName'] ?? ''),
                'toProductId'     => $cleanToPid,
                'toProductName'   => (string) ($row['toProductName'] ?? ''),
                'fromQuantity'    => $fromQuantity,
                'sessionFromQty'  => $sessionFromQty,
                'toQuantity'      => is_numeric($row['toQuantity'] ?? null) ? (float) $row['toQuantity'] : 0.0,
                'note'            => (string) ($row['note'] ?? ''),
                'createdAt'       => is_numeric($row['createdAt'] ?? null) ? (int) $row['createdAt'] : time(),
                'updatedAt'       => is_numeric($row['updatedAt'] ?? null) ? (int) $row['updatedAt'] : time(),
            ];
        }, $rows);
    }

    /**
     * Cast tất cả string values từ Apps Script về đúng type PHP.
     * Apps Script luôn trả string kể cả số và timestamp.
     *
     * @param array[] $rows
     * @return array[]
     */
    private function castRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id'            => ltrim((string) ($row['id'] ?? ''), "'"),
                'date'          => (string) ($row['date'] ?? ''),
                'productId'     => ltrim((string) ($row['productId'] ?? ''), "'"),
                'productName'   => (string) ($row['productName'] ?? ''),
                'quantity'      => is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : 0,
                'sellingPrice'  => is_numeric($row['sellingPrice'] ?? null) ? (float) $row['sellingPrice'] : 0,
                'purchasePrice' => is_numeric($row['purchasePrice'] ?? null) ? (float) $row['purchasePrice'] : 0,
                'note'          => (string) ($row['note'] ?? ''),
                'createdAt'     => is_numeric($row['createdAt'] ?? null) ? (int) $row['createdAt'] : time(),
                'updatedAt'     => is_numeric($row['updatedAt'] ?? null) ? (int) $row['updatedAt'] : time(),
            ];
        }, $rows);
    }
}

