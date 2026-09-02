<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * OwnerPetRepository — thay thế Application\Model\OwnerPet
 */
class OwnerPetRepository extends BaseRepository
{
    private const TABLE = 'owners_pets';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM owners_pets ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM owners_pets WHERE id = ?", [$id]);
    }

    public function getDataToView(): array
    {
        return $this->getData();
    }

    /**
     * Tìm kiếm theo tên thú cưng — SQL LIKE case-insensitive.
     * Trả về format Select2: [{id, text}]
     */
    public function searchByPetName(string $keyword): array
    {
        if (trim($keyword) === '') {
            return [];
        }
        $rows = $this->fetchAll(
            "SELECT * FROM owners_pets WHERE pet_name LIKE ? ORDER BY pet_name ASC",
            ['%' . $keyword . '%']
        );
        return $rows;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public function doAdd(array $postData): string
    {
        $id  = $this->generateId();
        $now = $this->ts();

        $this->execute("
            INSERT INTO owners_pets
                (id, owner_name, phone, pet_name, species, breed, gender, age, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $id,
            trim($postData['owner_name'] ?? ''),
            trim($postData['phone']      ?? ''),
            trim($postData['pet_name']   ?? ''),
            trim($postData['species']    ?? ''),
            trim($postData['breed']      ?? ''),
            trim($postData['gender']     ?? ''),
            trim($postData['age']        ?? ''),
            trim($postData['note']       ?? ''),
            $now, $now,
        ]);

        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute("
            UPDATE owners_pets SET
                owner_name = ?, phone = ?, pet_name = ?,
                species = ?, breed = ?, gender = ?,
                age = ?, note = ?, updatedAt = ?
            WHERE id = ?
        ", [
            trim($postData['owner_name'] ?? ''),
            trim($postData['phone']      ?? ''),
            trim($postData['pet_name']   ?? ''),
            trim($postData['species']    ?? ''),
            trim($postData['breed']      ?? ''),
            trim($postData['gender']     ?? ''),
            trim($postData['age']        ?? ''),
            trim($postData['note']       ?? ''),
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
