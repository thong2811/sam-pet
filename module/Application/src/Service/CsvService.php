<?php

namespace Application\Service;

use League\Csv\Reader;
use League\Csv\Writer;
use MongoDB\BSON\UTCDateTime;

class CsvService
{
    private $filePath;
    private $headers;

    const DEFAULT_HEADERS = ['createdAt', 'updatedAt'];

    protected function __construct(array $csvConstruct)
    {
        $this->filePath = './data/' . $csvConstruct['fileName'];
        $this->headers = array_merge($csvConstruct['header'], self::DEFAULT_HEADERS);

        if (!file_exists($this->filePath)) {
            $this->createFile();
        }

        $this->checkHeaders();
    }

    public function createFile()
    {
        $dir = dirname($this->filePath);
        mkdir($dir, 0777, true);
    }

    public function checkHeaders()
    {
        $reader = Reader::createFromPath($this->filePath, 'r');

        if (filesize($this->filePath) == 0) {
            $writer = Writer::createFromPath($this->filePath, 'w');
            $writer->insertOne($this->headers);

            return;
        }

        $reader->setHeaderOffset(0);
        $fileHeaders = $reader->getHeader();
        $data = [];

        if (count($fileHeaders) !== count($this->headers) || !empty(array_diff($this->headers, $fileHeaders))) {
            $csvData = iterator_to_array($reader->getRecords());

            foreach ($csvData as $row) {
                $data[] = $this->mappingDataWithHeaders($row);
            }

            $this->saveData($data);
        }
    }

    public function getData()
    {
        $data = [];
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

    public function getDataByDate($date)
    {
        $data = $this->getData();

        return array_filter($data, function ($row) use ($date) {
            return isset($row['date']) && $row['date'] == $date;
        });;
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
        $csv = Writer::createFromPath($this->filePath, 'w');
        $csv->insertOne($this->headers);
        $csv->insertAll($data);
    }

    public function addRow($row)
    {
        $csv = Writer::createFromPath($this->filePath, 'a');

        if (empty($row['id'])) {
            $row['id'] = self::generateId();
        }

        $row['createdAt'] = time();
        $row['updatedAt'] = time();
        $row = $this->mappingDataWithHeaders($row);

        $csv->insertOne($row);
    }

    public function addRows($rows)
    {
        if (!count($rows)) {
            return;
        }

        $data = $this->getData();
        foreach ($rows as $row) {

            if (empty($row['id'])) {
                $row['id'] = self::generateId();
            }

            $row['createdAt'] = time();
            $row['updatedAt'] = time();

            $row = $this->mappingDataWithHeaders($row);
            $data[$row['id']] = $row;
        }

        $this->saveData($data);
    }

    public function updateRows($rows)
    {
        if (!count($rows)) {
            return;
        }

        $data = $this->getData();

        foreach ($rows as $id => $row) {
            $row['updatedAt'] = time();
            $row = $this->prepareRowToUpdate($data, $id, $row);

            $data[$id] = $row;
        }

        $this->saveData($data);
    }

    public function updateRow($id, $row)
    {
        $data = $this->getData();

        $row['updatedAt'] = time();
        $row = $this->prepareRowToUpdate($data, $id, $row);

        $data[$id] = $row;
        $this->saveData($data);
    }

    public function deleteDataById($id)
    {
        $data = $this->getData();

        if (isset($data[$id])) {
            unset($data[$id]);

            $this->saveData($data);
        }
    }

    public function mappingDataWithHeaders($data)
    {
        $result = [];

        foreach ($this->headers as $header) {
            $result[$header] = $data[$header] ?? '';
        }

        return $result;
    }

    public function prepareRowToUpdate($data, $id, $rowUpdate) {
        $row = $data[$id] ?? [];

        foreach ($this->headers as $key) {
            if (isset($rowUpdate[$key])) {
                $row[$key] = $rowUpdate[$key];
            }
        }

        return $row;
    }
    
    public static function generateId()
    {
        return uniqid();
    }
}
