<?php

declare(strict_types=1);

namespace Application\Repository;

use Application\Database\Database;

/**
 * BaseRepository — lớp cha cho tất cả Repository.
 *
 * Cung cấp:
 *  - Truy cập Database qua DI
 *  - generateId(): uniqid hex tương thích với ID cũ từ CSV
 *  - Helper ts(): Unix timestamp hiện tại
 *  - Các shortcut: fetchAll, fetchOne, fetchScalar, execute
 */
abstract class BaseRepository
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // ----------------------------------------------------------------
    // ID generation — giống LeagueCsv::generateId()
    // ----------------------------------------------------------------

    /**
     * Tạo ID mới — format uniqid() hex 13 ký tự, tương thích với CSV cũ.
     */
    protected function generateId(): string
    {
        return uniqid();
    }

    // ----------------------------------------------------------------
    // Timestamp helper
    // ----------------------------------------------------------------

    protected function ts(): int
    {
        return time();
    }

    // ----------------------------------------------------------------
    // Query shortcuts (delegate đến Database)
    // ----------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->db->fetchOne($sql, $params);
    }

    protected function fetchScalar(string $sql, array $params = []): mixed
    {
        return $this->db->fetchScalar($sql, $params);
    }

    protected function execute(string $sql, array $params = []): \PDOStatement
    {
        return $this->db->execute($sql, $params);
    }

    // ----------------------------------------------------------------
    // Shared delete helper
    // ----------------------------------------------------------------

    /**
     * Xóa row theo id.
     * @param string $table  Tên bảng (đã được validate bởi subclass — không dùng user input)
     */
    public function deleteRow(string $table, string $id): bool
    {
        $stmt = $this->execute("DELETE FROM $table WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Lấy row theo id.
     * @param string $table  Tên bảng
     * @return array<string, mixed>|null
     */
    public function getById(string $table, string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM $table WHERE id = ?", [$id]);
    }
}
