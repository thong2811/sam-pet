<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class MedicalRecord extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'pet_id', 'visit_date', 'symptoms', 'diagnosis', 'prescription', 'start_date', 'end_date'],
        'fileName' => 'medical_records.csv'
    ];

    public function __construct()
    {
        parent::__construct(self::CSV_CONSTRUCT);
    }

    public function doAdd($postData)
    {
        $this->addRow($postData);
    }

    public function doEdit($postData)
    {
        $this->updateRow($postData);
    }

    public function getHistoryByPetId($petId)
    {
        return $this->getDataByKey('pet_id', $petId);
    }

    public function getDataToView()
    {
        $ownerPetModel = new OwnerPet();
        $ownerPetData = $ownerPetModel->getData();

        $data = $this->getData();
        foreach ($data as $id => &$row) {
            $petId = $row['pet_id'] ?? '';
            $pet = $ownerPetData[$petId] ?? [];
            $row['pet_name'] = $pet['pet_name'] ?? '';
            $row['owner_name'] = $pet['owner_name'] ?? '';
            $row['action'] = sprintf(
                '<a href="/medical-record/edit/%s" class="btn btn-primary btn-sm">Sửa</a> ', $id)
                . sprintf('<button class="btn btn-danger btn-sm" onclick="remove(\'%s\')">Xóa</button>', $id);
        }
        return $data;
    }
}
