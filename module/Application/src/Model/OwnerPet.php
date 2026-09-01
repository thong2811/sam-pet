<?php

namespace Application\Model;

use Application\Library\LeagueCsv;

class OwnerPet extends LeagueCsv
{
    public const CSV_CONSTRUCT = [
        'header' => ['id', 'owner_name', 'phone', 'pet_name', 'species', 'breed', 'gender', 'age', 'note'],
        'fileName' => 'owners_pets.csv'
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

    public function searchByPetName($petName)
    {
        $data = $this->getData();
        return array_filter($data, function ($row) use ($petName) {
            return isset($row['pet_name']) && stripos($row['pet_name'], $petName) !== false;
        });
    }

    public function getDataToView()
    {
        $data = $this->getData();
        foreach ($data as $id => &$row) {
            // action column được render phía client trong DataTable
        }
        return $data;
    }
}
