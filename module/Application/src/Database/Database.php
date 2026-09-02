<?php

declare(strict_types=1);

namespace Application\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database — Lớp truy cập SQLite duy nhất trong toàn ứng dụng.
 *
 * Tính năng:
 *  - Kết nối PDO với SQLite, bật WAL mode để hỗ trợ concurrent reads
 *  - Transaction helpers: beginTransaction / commit / rollback
 *  - Migration system dựa trên PRAGMA user_version
 *  - Tự tạo DB + chạy migration lần đầu nếu file chưa tồn tại
 *
 * Sử dụng:
 *   $db = $container->get(Database::class);
 *   $db->fetchAll('SELECT * FROM products WHERE invoiceCheck = ?', ['1']);
 */
class Database
{
    private PDO $pdo;

    /** Thư mục chứa các file SQL migration: data/migrations/001_xxx.sql */
    private string $migrationsDir;

    /** Đường dẫn tới file SQLite */
    private string $dbPath;

    public function __construct(string $dbPath = null, string $migrationsDir = null)
    {
        $this->dbPath        = $dbPath        ?? (getcwd() . '/data/app.db');
        $this->migrationsDir = $migrationsDir ?? (getcwd() . '/data/migrations');

        $this->connect();
        $this->migrate();
    }

    // ----------------------------------------------------------------
    // Kết nối
    // ----------------------------------------------------------------

    private function connect(): void
    {
        $dbDir = dirname($this->dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $this->dbPath);

        // Ném exception thay vì trả về false — bắt lỗi rõ ràng hơn
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Trả về array kết hợp, không cần gọi PDO::FETCH_ASSOC mỗi lần
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // WAL mode: cho phép nhiều readers đọc đồng thời trong khi writer đang ghi
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        // Bật foreign key enforcement (SQLite tắt mặc định)
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Tăng timeout chờ lock để tránh "database is locked" khi backup
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
    }

    // ----------------------------------------------------------------
    // Migration system
    // ----------------------------------------------------------------

    /**
     * Chạy các migration còn thiếu theo thứ tự tăng dần của version.
     * Mỗi file migration có tên dạng: 001_initial_schema.sql, 002_xxx.sql, ...
     * PRAGMA user_version lưu version hiện tại của schema.
     */
    public function migrate(): void
    {
        $currentVersion = $this->getUserVersion();
        $migrations     = $this->getPendingMigrations($currentVersion);

        if (empty($migrations)) {
            return;
        }

        foreach ($migrations as $version => $file) {
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException("Không thể đọc file migration: $file");
            }

            // Chạy toàn bộ script trong một transaction
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $this->setUserVersion($version);
                $this->pdo->commit();
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw new RuntimeException(
                    "Migration v$version thất bại: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }
    }

    /** Lấy version hiện tại của schema từ PRAGMA */
    public function getUserVersion(): int
    {
        $result = $this->pdo->query('PRAGMA user_version')->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['user_version'] ?? 0);
    }

    /** Ghi version mới vào PRAGMA (phải được gọi trong transaction) */
    private function setUserVersion(int $version): void
    {
        // PRAGMA user_version không nhận prepared statement — dùng exec trực tiếp
        // version là integer nội bộ, không có injection risk
        $this->pdo->exec("PRAGMA user_version = $version");
    }

    /**
     * Tìm các file migration có version > currentVersion.
     * Tên file phải có prefix số: 001_xxx.sql, 002_xxx.sql, ...
     *
     * @return array<int, string>  [version => filePath]
     */
    private function getPendingMigrations(int $currentVersion): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files   = glob($this->migrationsDir . '/*.sql');
        $pending = [];

        foreach ($files as $file) {
            $basename = basename($file);
            // Lấy số ở đầu tên file: "001_initial_schema.sql" → 1
            if (!preg_match('/^(\d+)_/', $basename, $matches)) {
                continue;
            }
            $version = (int) $matches[1];
            if ($version > $currentVersion) {
                $pending[$version] = $file;
            }
        }

        ksort($pending);
        return $pending;
    }

    // ----------------------------------------------------------------
    // Transaction helpers
    // ----------------------------------------------------------------

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Thực thi một callable trong transaction.
     * Tự động commit khi thành công, rollback khi có exception.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // Query helpers
    // ----------------------------------------------------------------

    /**
     * Chuẩn bị và thực thi câu query với tham số.
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Lấy tất cả rows dưới dạng array kết hợp.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /**
     * Lấy một row duy nhất hoặc null nếu không tìm thấy.
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->execute($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Lấy một giá trị scalar từ cột đầu tiên của row đầu tiên.
     */
    public function fetchScalar(string $sql, array $params = []): mixed
    {
        $result = $this->execute($sql, $params)->fetch(PDO::FETCH_NUM);
        return $result === false ? null : $result[0];
    }

    /**
     * Số rows bị ảnh hưởng bởi câu lệnh INSERT / UPDATE / DELETE gần nhất.
     */
    public function rowCount(PDOStatement $stmt): int
    {
        return $stmt->rowCount();
    }

    /**
     * ID của row vừa INSERT (lastInsertId).
     * Với TEXT PK không dùng AUTOINCREMENT, method này ít hữu ích —
     * nhưng giữ lại để tiện dùng khi cần.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // Backup helper (dùng cho BackupService)
    // ----------------------------------------------------------------

    /**
     * Tạo bản sao database sang file đích bằng SQLite VACUUM INTO.
     * VACUUM INTO đảm bảo file đích là bản sao nhất quán dù đang có writes.
     *
     * @param string $destPath  Đường dẫn file backup đích (tuyệt đối)
     */
    public function vacuumInto(string $destPath): void
    {
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // VACUUM INTO không nhận prepared statement
        $escaped = str_replace("'", "''", $destPath);
        $this->pdo->exec("VACUUM INTO '$escaped'");
    }

    // ----------------------------------------------------------------
    // Utility
    // ----------------------------------------------------------------

    /**
     * Lấy kích thước file DB tính bằng bytes.
     */
    public function getDbSize(): int
    {
        return file_exists($this->dbPath) ? filesize($this->dbPath) : 0;
    }

    public function getDbPath(): string
    {
        return $this->dbPath;
    }

    /**
     * Truy cập PDO gốc khi cần thao tác nâng cao.
     * Hạn chế sử dụng bên ngoài — ưu tiên dùng các helper ở trên.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
