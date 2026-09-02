<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Database\Database;

/**
 * BackupService v2 — backup/restore file app.db thay vì ZIP CSV.
 *
 * Thay đổi so với v1:
 *  - Asset GitHub: backup.db (thay vì backup.zip)
 *  - backup(): dùng Database::vacuumInto() tạo bản sao nhất quán
 *  - restore(): download backup.db, replace app.db
 *  - backupForStocktaking(): vacuumInto() vào data/backup_stocktaking/
 *  - Không còn ZipArchive dependency
 */
class BackupService
{
    private const ASSET_NAME    = 'backup.db';
    private const GITHUB_API    = 'https://api.github.com';
    private const GITHUB_UPLOAD = 'https://uploads.github.com';

    private string $token;
    private string $owner;
    private string $repo;
    private string $releaseTag;
    private string $dataDir;
    private string $dbPath;
    private string $cachePath;   // temp file for upload/download

    public function __construct()
    {
        $this->token      = (string) ($_ENV['GITHUB_TOKEN']      ?? '');
        $this->owner      = (string) ($_ENV['GITHUB_REPO_OWNER'] ?? '');
        $this->repo       = (string) ($_ENV['GITHUB_REPO_NAME']  ?? '');
        $env              = strtolower((string) ($_ENV['APP_ENV'] ?? 'dev'));
        $this->releaseTag = 'data-backup-' . ($env === 'prod' ? 'prod' : 'dev');

        $this->dataDir   = rtrim(
            realpath(__DIR__ . '/../../../../data') ?: __DIR__ . '/../../../../data',
            '/\\'
        );
        $this->dbPath    = $this->dataDir . DIRECTORY_SEPARATOR . 'app.db';
        $this->cachePath = $this->dataDir . DIRECTORY_SEPARATOR . 'cache'
                         . DIRECTORY_SEPARATOR . self::ASSET_NAME;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Tạo bản sao app.db (VACUUM INTO) rồi upload lên GitHub Releases.
     * Được gọi sau response trả về client — không block UX.
     * Lỗi chỉ ghi log, không throw.
     */
    public function backup(): void
    {
        try {
            $this->validateConfig();
            $this->createDbBackup($this->cachePath);
            $this->uploadToGithub();

            CommonService::logger($this->logPath())->info(
                'Backup thành công',
                ['asset' => self::ASSET_NAME, 'release' => $this->releaseTag]
            );
        } catch (\Throwable $e) {
            CommonService::logger($this->logPath())->error(
                'Backup thất bại: ' . $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );
        } finally {
            if (file_exists($this->cachePath)) {
                @unlink($this->cachePath);
            }
        }
    }

    /**
     * Download backup.db từ GitHub Releases và replace app.db.
     *
     * @throws \RuntimeException nếu download hoặc replace thất bại
     */
    public function restore(): void
    {
        $downloadUrl = $this->getAssetDownloadUrl();

        $restorePath = $this->dataDir . DIRECTORY_SEPARATOR . 'cache'
                     . DIRECTORY_SEPARATOR . 'backup_restore.db';

        $this->ensureDir(dirname($restorePath));
        $this->downloadFile($downloadUrl, $restorePath);

        // Kiểm tra file tải về có phải SQLite hợp lệ
        if (!$this->isValidSqlite($restorePath)) {
            @unlink($restorePath);
            throw new \RuntimeException('File backup.db tải về không phải SQLite hợp lệ.');
        }

        // Atomically replace app.db
        if (!rename($restorePath, $this->dbPath)) {
            // Fallback nếu rename cross-device
            if (!copy($restorePath, $this->dbPath)) {
                throw new \RuntimeException('Không thể thay thế app.db bằng file backup.');
            }
            @unlink($restorePath);
        }

        CommonService::logger($this->logPath())->info(
            'Restore thành công',
            ['source' => self::ASSET_NAME, 'dest' => $this->dbPath]
        );
    }

    /**
     * Backup trước khi chốt kho — lưu local, không upload GitHub.
     * Được gọi từ StocktakingRepository::renewWarehouse().
     *
     * @deprecated Inject Database trực tiếp và dùng Database::vacuumInto()
     *             StocktakingRepository tự gọi db->vacuumInto() nên method này
     *             còn giữ cho backward compat với CommonService::backupDataToStocktaking()
     */
    public function backupForStocktaking(): bool
    {
        try {
            $backupDir = $this->dataDir . DIRECTORY_SEPARATOR . 'backup_stocktaking';
            $this->ensureDir($backupDir);

            $backupPath = $backupDir . DIRECTORY_SEPARATOR
                        . 'backup_' . date('Ymd_His') . '.db';

            $db = new Database($this->dbPath);
            $db->vacuumInto($backupPath);
            return true;
        } catch (\Throwable $e) {
            CommonService::logger($this->logPath())->error(
                'backupForStocktaking thất bại: ' . $e->getMessage()
            );
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // DB backup helpers
    // -------------------------------------------------------------------------

    /**
     * Tạo bản sao DB nhất quán bằng VACUUM INTO.
     * Đảm bảo file đích là snapshot hoàn chỉnh dù đang có writes.
     */
    private function createDbBackup(string $destPath): void
    {
        $this->ensureDir(dirname($destPath));

        if (!file_exists($this->dbPath)) {
            throw new \RuntimeException('Không tìm thấy app.db tại: ' . $this->dbPath);
        }

        $db = new Database($this->dbPath);
        $db->vacuumInto($destPath);
    }

    /**
     * Kiểm tra file có phải SQLite bằng magic header "SQLite format 3\000".
     */
    private function isValidSqlite(string $path): bool
    {
        if (!file_exists($path) || filesize($path) < 16) {
            return false;
        }
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 16);
        fclose($handle);
        return str_starts_with((string) $header, 'SQLite format 3');
    }

    // -------------------------------------------------------------------------
    // GitHub Releases API
    // -------------------------------------------------------------------------

    public function uploadToGithub(): void
    {
        $releaseId = $this->upsertRelease();
        $this->deleteExistingAsset($releaseId);
        $this->uploadAsset($releaseId);
    }

    private function upsertRelease(): int
    {
        $url      = sprintf('%s/repos/%s/%s/releases/tags/%s',
            self::GITHUB_API, $this->owner, $this->repo, $this->releaseTag);
        $response = $this->curlRequest('GET', $url);

        if (isset($response['id'])) {
            return (int) $response['id'];
        }

        $url      = sprintf('%s/repos/%s/%s/releases', self::GITHUB_API, $this->owner, $this->repo);
        $payload  = [
            'tag_name'   => $this->releaseTag,
            'name'       => 'Data Backup [' . strtoupper(str_replace('data-backup-', '', $this->releaseTag)) . ']',
            'body'       => 'Backup tự động app.db — môi trường ' . str_replace('data-backup-', '', $this->releaseTag) . '.',
            'draft'      => false,
            'prerelease' => false,
        ];
        $response = $this->curlRequest('POST', $url, json_encode($payload), 'application/json');

        if (!isset($response['id'])) {
            throw new \RuntimeException('Tạo GitHub Release thất bại: ' . json_encode($response));
        }

        return (int) $response['id'];
    }

    private function deleteExistingAsset(int $releaseId): void
    {
        $url      = sprintf('%s/repos/%s/%s/releases/%d/assets',
            self::GITHUB_API, $this->owner, $this->repo, $releaseId);
        $response = $this->curlRequest('GET', $url);

        if (!is_array($response)) {
            return;
        }

        foreach ($response as $asset) {
            if (($asset['name'] ?? '') === self::ASSET_NAME) {
                $deleteUrl = sprintf('%s/repos/%s/%s/releases/assets/%d',
                    self::GITHUB_API, $this->owner, $this->repo, (int) $asset['id']);
                $this->curlRequest('DELETE', $deleteUrl);
                break;
            }
        }
    }

    private function uploadAsset(int $releaseId): void
    {
        $url      = sprintf('%s/repos/%s/%s/releases/%d/assets?name=%s',
            self::GITHUB_UPLOAD, $this->owner, $this->repo, $releaseId, self::ASSET_NAME);
        $fileData = file_get_contents($this->cachePath);

        if ($fileData === false) {
            throw new \RuntimeException('Không thể đọc file backup: ' . $this->cachePath);
        }

        $response = $this->curlRequest('POST', $url, $fileData, 'application/octet-stream');

        if (!isset($response['id'])) {
            throw new \RuntimeException('Upload asset thất bại: ' . json_encode($response));
        }
    }

    private function getAssetDownloadUrl(): string
    {
        $url      = sprintf('%s/repos/%s/%s/releases/tags/%s',
            self::GITHUB_API, $this->owner, $this->repo, $this->releaseTag);
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

    private function curlRequest(string $method, string $url, string $body = '', string $contentType = 'application/json'): array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: sam-pet-backup/2.0',
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

        if ($httpCode === 204) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'GitHub API trả về không phải JSON (HTTP ' . $httpCode . '): ' . substr($raw, 0, 200)
            );
        }

        return (array) $decoded;
    }

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
    // Utility
    // -------------------------------------------------------------------------

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

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
