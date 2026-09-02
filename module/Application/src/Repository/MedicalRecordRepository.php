<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * MedicalRecordRepository — thay thế Application\Model\MedicalRecord
 */
class MedicalRecordRepository extends BaseRepository
{
    private const TABLE = 'medical_records';

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM medical_records WHERE id = ?", [$id]);
    }

    /**
     * Lịch sử khám của một thú cưng, sắp xếp mới nhất lên đầu.
     */
    public function getHistoryByPetId(string $petId): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM medical_records WHERE pet_id = ?
             ORDER BY SUBSTR(visit_date,7,4)||SUBSTR(visit_date,4,2)||SUBSTR(visit_date,1,2) DESC",
            [$petId]
        );
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    /**
     * Danh sách tất cả hồ sơ khám — JOIN với owners_pets để lấy pet_name, owner_name, species.
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("
            SELECT mr.*,
                   op.pet_name   AS pet_name,
                   op.owner_name AS owner_name,
                   op.species    AS species
            FROM medical_records mr
            LEFT JOIN owners_pets op ON op.id = mr.pet_id
            ORDER BY mr.createdAt DESC
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

        $this->execute("
            INSERT INTO medical_records
                (id, pet_id, visit_date, symptoms, diagnosis, prescription,
                 start_date, end_date, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $id,
            trim($postData['petId']       ?? $postData['pet_id'] ?? ''),
            trim($postData['visit_date']  ?? ''),
            trim($postData['symptoms']    ?? ''),
            trim($postData['diagnosis']   ?? ''),
            trim($postData['prescription'] ?? ''),
            trim($postData['start_date']  ?? ''),
            trim($postData['end_date']    ?? ''),
            $now, $now,
        ]);

        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute("
            UPDATE medical_records SET
                visit_date = ?, symptoms = ?, diagnosis = ?,
                prescription = ?, start_date = ?, end_date = ?,
                updatedAt = ?
            WHERE id = ?
        ", [
            trim($postData['visit_date']   ?? ''),
            trim($postData['symptoms']     ?? ''),
            trim($postData['diagnosis']    ?? ''),
            trim($postData['prescription'] ?? ''),
            trim($postData['start_date']   ?? ''),
            trim($postData['end_date']     ?? ''),
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
