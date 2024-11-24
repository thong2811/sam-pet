<?php

namespace Application\Service;

use League\Csv\Reader;
use League\Csv\Writer;

class CsvService
{
    private $filePath;
    private $headers;

    protected function __construct(array $csvConstruct)
    {
        $this->filePath = './data/' . $csvConstruct['fileName'];
        $this->headers = $csvConstruct['header'];
    }

    public function getData()
    {
        $data = [];
        if (!file_exists($this->filePath)) {
            $this->createFile();
            return $data;
        }

        $csv = Reader::createFromPath($this->filePath, 'r');
        $csv->setHeaderOffset(0);
        $csvData = iterator_to_array($csv->getRecords());
        foreach ($csvData as $row) {
            if (empty($row['id'])) {
                continue;
            }
            $data[$row['id']] = $row;
        }
        return $data;
    }

    public function getDataById($id)
    {
        $data = $this->getData();
        foreach ($data as $row) {
            if (isset($row['id']) && $row['id'] = $id) {
                return $row;
            }
        }

        return null;
    }

    public function saveData($data)
    {
        if (!file_exists($this->filePath)) {
            $this->createFile();
        }

        $csv = Writer::createFromPath($this->filePath, 'w');
        $csv->insertOne($this->headers);
        $csv->insertAll($data);
    }

    public function addRow($row)
    {
        if (!file_exists($this->filePath)) {
            $this->createFile();
        }

        $csv = Writer::createFromPath($this->filePath, 'a');

        if (filesize($this->filePath) == 0) {
            $csv->insertOne($this->headers);
        }

        $row['id'] = $this->generateId();
        $row = $this->prepareData($row);

        $csv->insertOne($row);
    }

    public function updateDataById($updateData)
    {
        $updateData = $this->prepareData($updateData);
        $data = $this->getData();
        if (isset($data[$updateData['id']])) {
            $data[$updateData['id']] = $updateData;
            $this->saveData($data);
        }
    }

    public function deleteDataById($id)
    {
        $data = $this->getData();
        if (isset($data[$id])) {
            unset($data[$id]);
            $this->saveData($data);
        }
    }

    public function prepareData($data) {
        $result = [];
        foreach ($this->headers as $header) {
            $result[$header] = $data[$header] ?? '';
        }
        return $result;
    }

    public function createFile()
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $csv = Writer::createFromPath($this->filePath, 'w');
        $csv->insertOne($this->headers);
    }

    public function generateId(): string
    {
        return uniqid();
    }
}
