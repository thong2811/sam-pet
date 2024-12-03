<?php

namespace Application\Service;

use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use function PHPUnit\Framework\directoryExists;

class CsvService
{
    private $filePath;
    private $headers;

    const DEFAULT_HEADERS = ['createdAt', 'updatedAt'];
    const DEFAULT_PRIMARY_KEY = 'id';

    protected function __construct(array $csvConstruct)
    {
        $this->filePath = './data/' . $csvConstruct['fileName'];
        $this->headers = array_merge($csvConstruct['header'], self::DEFAULT_HEADERS);
        $this->primaryKey = $csvConstruct['primaryKey'] ?? self::DEFAULT_PRIMARY_KEY;

        if (!file_exists($this->filePath)) {
            $this->createFile();
        }

        $this->checkHeaders();
    }

    public function createFile()
    {
        $dir = dirname($this->filePath);
        if (!directoryExists($dir)) {
            mkdir($dir, 0777, true);
        }

        Writer::createFromPath($this->filePath, 'w');
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
            if (empty($row[$this->primaryKey])) {
                continue;
            }

            $data[$row[$this->primaryKey]] = $row;
        }

        return $data;
    }

    public function getDataByKey($key, $value)
    {
        $data = $this->getData();

        return array_filter($data, function ($row) use ($key, $value) {
            return isset($row[$key]) && $row[$key] == $value;
        });
    }

    public function getDataById($id)
    {
        $data = $this->getData();

        foreach ($data as $row) {
            if (isset($row[$this->primaryKey]) && $row[$this->primaryKey] == $id) {
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
        $this->addRows([$row]);
    }

    public function addRows($rows)
    {
        if (!count($rows)) {
            return;
        }

        $data = $this->getData();
        foreach ($rows as $row) {

            if (empty($row[$this->primaryKey])) {
                $row[$this->primaryKey] = self::generateId();
            }

            $row['createdAt'] = time();
            $row['updatedAt'] = time();

            $row = $this->mappingDataWithHeaders($row);
            $data[$row[$this->primaryKey]] = $row;
        }

        $this->saveData($data);
    }

    public function updateRows($rows)
    {
        if (!count($rows)) {
            return;
        }

        $data = $this->getData();

        foreach ($rows as $row) {
            if (!isset($row[$this->primaryKey])) {
                throw new Exception("Không tìm thấy khóa chính để cập nhật: " . $this->primaryKey);
            }

            $id = $row[$this->primaryKey];
            $row['updatedAt'] = time();
            $row = $this->prepareRowToUpdate($data, $id, $row);

            $data[$id] = $row;
        }

        $this->saveData($data);
    }

    public function updateRow($row)
    {
        $this->updateRows([$row]);
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
