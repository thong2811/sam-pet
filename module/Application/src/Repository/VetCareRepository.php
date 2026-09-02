<?php

declare(strict_types=1);

namespace Application\Repository;

/**
 * VetCareRepository — thay thế Application\Model\VetCare
 */
class VetCareRepository extends BaseRepository
{
    private const TABLE = 'vet_care';

    public const TREATMENT_PROFIT_PERCENT = 0.4;

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    public function getData(): array
    {
        $rows = $this->fetchAll("SELECT * FROM vet_care ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById(string $id): ?array
    {
        return $this->fetchOne("SELECT * FROM vet_care WHERE id = ?", [$id]);
    }

    /**
     * [date => ['treatment' => ..., 'spa' => ..., 'treatmentProfit' => ...]]
     */
    public function totalAmountByDate(): array
    {
        $rows = $this->fetchAll("
            SELECT date,
                   SUM(treatmentAmount) AS treatment,
                   SUM(spaAmount)       AS spa
            FROM vet_care
            GROUP BY date
        ");
        $result = [];
        foreach ($rows as $row) {
            $treatment = (float) $row['treatment'];
            $result[$row['date']] = [
                'treatment'      => $treatment,
                'spa'            => (float) $row['spa'],
                'treatmentProfit'=> $treatment * self::TREATMENT_PROFIT_PERCENT,
            ];
        }
        return $result;
    }

    /**
     * Thêm field `total = treatmentAmount + spaAmount`.
     */
    public function getDataToView(): array
    {
        $rows = $this->fetchAll("SELECT * FROM vet_care ORDER BY createdAt DESC");
        $data = [];
        foreach ($rows as $row) {
            $row['total'] = (float) $row['treatmentAmount'] + (float) $row['spaAmount'];
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
            INSERT INTO vet_care (id, date, treatmentAmount, spaAmount, note, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $id,
            trim($postData['date']            ?? ''),
            (float) ($postData['treatmentAmount'] ?? 0),
            (float) ($postData['spaAmount']       ?? 0),
            trim($postData['note']            ?? ''),
            $now, $now,
        ]);
        return $id;
    }

    public function doEdit(array $postData): void
    {
        $this->execute("
            UPDATE vet_care SET
                date = ?, treatmentAmount = ?, spaAmount = ?, note = ?, updatedAt = ?
            WHERE id = ?
        ", [
            trim($postData['date']            ?? ''),
            (float) ($postData['treatmentAmount'] ?? 0),
            (float) ($postData['spaAmount']       ?? 0),
            trim($postData['note']            ?? ''),
            $this->ts(),
            $postData['id'],
        ]);
    }

    public function remove(string $id): bool
    {
        return $this->deleteRow(self::TABLE, $id);
    }
}
