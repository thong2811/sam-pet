<?php

declare(strict_types=1);

namespace Application\Service;

use ZipArchive;

class BackupService
{
    private const ASSET_NAME    = 'backup.zip';
    private const GITHUB_API    = 'https://api.github.com';
    private const GITHUB_UPLOAD = 'https://uploads.github.com';

    private string $token;
    private string $owner;
    private string $repo;
    private string $releaseTag;
    private string $dataDir;
    private string $zipPath;

    public function __construct()
    {
        $this->token      = (string) ($_ENV['GITHUB_TOKEN']      ?? '');
        $this->owner      = (string) ($_ENV['GITHUB_REPO_OWNER'] ?? '');
        $this->repo       = (string) ($_ENV['GITHUB_REPO_NAME']  ?? '');
        $env              = strtolower((string) ($_ENV['APP_ENV'] ?? 'dev'));
        $this->releaseTag = 'data-backup-' . ($env === 'prod' ? 'prod' : 'dev');
        // Tính absolute path mà không cần thư mục đích phải tồn tại trước
        $this->dataDir = rtrim(realpath(__DIR__ . '/../../../../data') ?: __DIR__ . '/../../../../data', '/\\');
        $this->zipPath = $this->dataDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . self::ASSET_NAME;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Zip /data rồi upload lên GitHub Releases.
     * Được gọi sau response trả về client (không block UX).
     * Lỗi chỉ ghi log, không throw.
     */
    public function backup(): void
    {
        try {
            $this->validateConfig();
            $fileCount = $this->createZip();
            $this->uploadToGithub();

            CommonService::logger($this->logPath())->info(
                'Backup thành công',
                ['files' => $fileCount, 'release' => $this->releaseTag]
            );
        } catch (\Throwable $e) {
            CommonService::logger($this->logPath())->error(
                'Backup thất bại: ' . $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );
        } finally {
            // Dọn file zip tạm dù thành công hay thất bại
            if (file_exists($this->zipPath)) {
                @unlink($this->zipPath);
            }
        }
    }

    /**
     * Tải backup.zip từ GitHub Releases về và giải nén vào /data.
     * Dùng public download URL — không cần token.
     *
     * @throws \RuntimeException nếu download hoặc giải nén thất bại
     */
    public function restore(): void
    {
        $downloadUrl = $this->getAssetDownloadUrl();

        // Tải file về /data/cache/backup_restore.zip
        $restorePath = $this->dataDir . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'backup_restore.zip';
        $this->downloadFile($downloadUrl, $restorePath);

        // Giải nén từng file CSV vào /data (overwrite)
        $zip = new ZipArchive();
        if ($zip->open($restorePath) !== true) {
            throw new \RuntimeException('Không thể mở file backup để giải nén.');
        }

        $restoredCount = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';

            // Chỉ lấy file CSV ở root, bỏ qua thư mục con và file khác
            if (str_contains($name, '/') || pathinfo($name, PATHINFO_EXTENSION) !== 'csv') {
                continue;
            }

            $destPath = $this->dataDir . DIRECTORY_SEPARATOR . basename($name);
            $content  = $zip->getFromIndex($i);

            if ($content === false) {
                continue;
            }

            file_put_contents($destPath, $content);
            $restoredCount++;
        }

        $zip->close();
        @unlink($restorePath);

        if ($restoredCount === 0) {
            throw new \RuntimeException('Backup không chứa file CSV nào để khôi phục.');
        }

        CommonService::logger($this->logPath())->info(
            'Restore thành công',
            ['files' => $restoredCount]
        );
    }

    // -------------------------------------------------------------------------
    // Stocktaking backup (local ZIP — không upload GitHub)
    // -------------------------------------------------------------------------

    /**
     * Tạo file ZIP backup tại data/backup_stocktaking/ trước khi chốt kho.
     * Không upload lên GitHub — chỉ lưu local.
     * Trả về true nếu thành công, false nếu thất bại.
     */
    public function backupForStocktaking(): bool
    {
        $sourceFolder = realpath(__DIR__ . '/../../../../data');
        if (!$sourceFolder) {
            return false;
        }

        $backupFolder   = $sourceFolder . DIRECTORY_SEPARATOR . 'backup_stocktaking';
        $backupFileName = 'backup_data_stocktaking_' . time() . '.zip';
        $backupFilePath = $backupFolder . DIRECTORY_SEPARATOR . $backupFileName;

        if (!is_dir($backupFolder)) {
            mkdir($backupFolder, 0777, true);
        }

        if (file_exists($backupFilePath)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($backupFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $files = scandir($sourceFolder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $filePath = $sourceFolder . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
                $zip->addFile($filePath, $file);
            }
        }

        $zip->close();
        return true;
    }

    // -------------------------------------------------------------------------
    // Zip
    // -------------------------------------------------------------------------

    /**
     * Zip toàn bộ file CSV trong /data (bỏ qua thư mục con và file backup).
     * Trả về số file được zip.
     */
    public function createZip(): int
    {
        $cacheDir = $this->dataDir . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Không thể tạo file zip tại: ' . $this->zipPath);
        }

        $fileCount = $this->addFilesToZip($zip, $this->dataDir, $this->dataDir);
        $zip->close();

        if ($fileCount === 0) {
            throw new \RuntimeException('Không có file nào được zip — /data trống.');
        }

        return $fileCount;
    }

    /**
     * Chỉ thêm file CSV ở root của /data vào zip.
     * Bỏ qua toàn bộ thư mục con (backup_stocktaking, backup, cache, mpdf...)
     * và các file không phải .csv.
     */
    private function addFilesToZip(ZipArchive $zip, string $dir, string $baseDir): int
    {
        $count = 0;
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

            // Chỉ lấy file CSV ở root /data, bỏ qua thư mục con và file khác
            if (!is_file($fullPath)) {
                continue;
            }

            if (pathinfo($fullPath, PATHINFO_EXTENSION) !== 'csv') {
                continue;
            }

            $zip->addFile($fullPath, $item);
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // GitHub Releases API
    // -------------------------------------------------------------------------

    /**
     * Upload file zip lên GitHub Releases (upsert release + overwrite asset).
     */
    public function uploadToGithub(): void
    {
        $releaseId = $this->upsertRelease();
        $this->deleteExistingAsset($releaseId);
        $this->uploadAsset($releaseId);
    }

    /**
     * Tìm release với tag data-backup, tạo mới nếu chưa có.
     * Trả về release ID.
     */
    private function upsertRelease(): int
    {
        $url      = sprintf('%s/repos/%s/%s/releases/tags/%s', self::GITHUB_API, $this->owner, $this->repo, $this->releaseTag);
        $response = $this->curlRequest('GET', $url);

        if (isset($response['id'])) {
            return (int) $response['id'];
        }

        // Release chưa tồn tại — tạo mới
        $url      = sprintf('%s/repos/%s/%s/releases', self::GITHUB_API, $this->owner, $this->repo);
        $payload  = [
            'tag_name'   => $this->releaseTag,
            'name'       => 'Data Backup [' . strtoupper(str_replace('data-backup-', '', $this->releaseTag)) . ']',
            'body'       => 'Backup tự động của toàn bộ dữ liệu CSV — môi trường ' . str_replace('data-backup-', '', $this->releaseTag) . '.',
            'draft'      => false,
            'prerelease' => false,
        ];
        $response = $this->curlRequest('POST', $url, json_encode($payload), 'application/json');

        if (!isset($response['id'])) {
            throw new \RuntimeException('Tạo GitHub Release thất bại: ' . json_encode($response));
        }

        return (int) $response['id'];
    }

    /**
     * Xóa asset backup.zip cũ trong release (nếu tồn tại).
     */
    private function deleteExistingAsset(int $releaseId): void
    {
        $url      = sprintf('%s/repos/%s/%s/releases/%d/assets', self::GITHUB_API, $this->owner, $this->repo, $releaseId);
        $response = $this->curlRequest('GET', $url);

        if (!is_array($response)) {
            return;
        }

        foreach ($response as $asset) {
            if (($asset['name'] ?? '') === self::ASSET_NAME) {
                $deleteUrl = sprintf('%s/repos/%s/%s/releases/assets/%d', self::GITHUB_API, $this->owner, $this->repo, (int) $asset['id']);
                $this->curlRequest('DELETE', $deleteUrl);
                break;
            }
        }
    }

    /**
     * Upload file zip lên release.
     */
    private function uploadAsset(int $releaseId): void
    {
        $url      = sprintf('%s/repos/%s/%s/releases/%d/assets?name=%s', self::GITHUB_UPLOAD, $this->owner, $this->repo, $releaseId, self::ASSET_NAME);
        $fileData = file_get_contents($this->zipPath);

        if ($fileData === false) {
            throw new \RuntimeException('Không thể đọc file zip: ' . $this->zipPath);
        }

        $response = $this->curlRequest('POST', $url, $fileData, 'application/zip');

        if (!isset($response['id'])) {
            throw new \RuntimeException('Upload asset thất bại: ' . json_encode($response));
        }
    }

    /**
     * Lấy download URL của asset backup.zip từ release mới nhất.
     */
    private function getAssetDownloadUrl(): string
    {
        $url      = sprintf('%s/repos/%s/%s/releases/tags/%s', self::GITHUB_API, $this->owner, $this->repo, $this->releaseTag);
        $response = $this->curlRequest('GET', $url);

        if (!isset($response['assets']) || !is_array($response['assets'])) {
            throw new \RuntimeException('Không tìm thấy release "' . $this->releaseTag . '" trên GitHub.');
        }

        foreach ($response['assets'] as $asset) {
            if (($asset['name'] ?? '') === self::ASSET_NAME) {
                return (string) $asset['browser_download_url'];
            }
        }

        throw new \RuntimeException('Không tìm thấy file ' . self::ASSET_NAME . ' trong release.');
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * Thực hiện HTTP request tới GitHub API bằng cURL.
     *
     * @return array Decoded JSON response
     */
    private function curlRequest(string $method, string $url, string $body = '', string $contentType = 'application/json'): array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: sam-pet-backup/1.0',
        ];

        if ($body !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            throw new \RuntimeException('cURL lỗi: ' . $curlErr);
        }

        // DELETE trả về 204 No Content — không có body JSON
        if ($httpCode === 204) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('GitHub API trả về không phải JSON (HTTP ' . $httpCode . '): ' . substr($raw, 0, 200));
        }

        return (array) $decoded;
    }

    /**
     * Download file từ URL về đường dẫn chỉ định.
     * Tự động follow redirect (GitHub asset URL redirect sang S3).
     */
    private function downloadFile(string $url, string $destPath): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $data    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($data === false || $curlErr !== '') {
            throw new \RuntimeException('Download backup thất bại: ' . $curlErr);
        }

        if (file_put_contents($destPath, $data) === false) {
            throw new \RuntimeException('Không thể ghi file tải về: ' . $destPath);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validateConfig(): void
    {
        $missing = [];
        if (empty($this->token)) $missing[] = 'GITHUB_TOKEN';
        if (empty($this->owner)) $missing[] = 'GITHUB_REPO_OWNER';
        if (empty($this->repo))  $missing[] = 'GITHUB_REPO_NAME';

        if (!empty($missing)) {
            throw new \RuntimeException('Thiếu cấu hình trong .env: ' . implode(', ', $missing));
        }
    }

    private function logPath(): string
    {
        return __DIR__ . '/../../../../logs/backup_' . date('Y-m') . '.log';
    }
}
