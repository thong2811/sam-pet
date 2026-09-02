<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * CategoryRepository — quản lý bảng categories (Nhóm 6)
 */
class CategoryRepository extends BaseRepository
{
    private const TABLE = 'categories';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM categories ORDER BY name ASC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);
    }

    /**
     * [id => name] — dùng cho dropdown chọn category.
     */
    public function getNameList(): array
    {
        $rows = $this->fetchAll("SELECT id, name FROM categories ORDER BY name ASC");
        $list = [];
        foreach ($rows as $row) {
            $list[$row['id']] = $row['name'];
        }
        return $list;
    }

    public function getDataToView(): array
    {
        // Đếm số sản phẩm trong mỗi category
        $rows = $this->fetchAll("
            SELECT c.*,
                   COUNT(p.id) AS productCount
            FROM categories c
            LEFT JOIN products p ON p.categoryId = c.id
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData): string
    {
        $id  = $this->generateId();
        $now = $this->ts();
        $this->execute(
            "INSERT INTO categories (id, name, note, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)",
            [$id, trim($postData['name'] ?? ''), trim($postData['note'] ?? ''), $now, $now]
        );
        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute(
            "UPDATE categories SET name = ?, note = ?, updatedAt = ? WHERE id = ?",
            [trim($postData['name'] ?? ''), trim($postData['note'] ?? ''), $this->ts(), $postData['id']]
        );
    }

    public function remove(string $id): bool
    {
        // Bỏ FK category khỏi products trước khi xóa
        $this->execute("UPDATE products SET categoryId = NULL WHERE categoryId = ?", [$id]);
        return $this->deleteRow(self::TABLE, $id);
    }
}
