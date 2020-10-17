<?php

namespace BackEndTea\MigrationHelper;

use Phinx\Migration\AbstractMigration;

abstract class CsvMigration extends AbstractMigration
{
    /**
     * Delimiter of the csv file, defaults to ','
     *
     * @var string
     */
    public $csvDelimiter = ',';

    /**
     * Row to start from
     *
     * @var int
     */
    public $offSetRows = 0;

    /**
     * @var string
     */
    private $fileName;

    public function insertCsv($table, $filename)
    {
        $this->fileName = $filename;
        $toInsert = $this->seedFromCSV();
        $this->table($table)->insert($toInsert)->save();
    }

    private function seedFromCSV()
    {
        $handle = $this->getFile();
        $data = [];
        ini_set('auto_detect_line_endings', 1);
        while (($csvRows = fgetcsv($handle, 0, $this->csvDelimiter)) !== false) {
            $data[] = $csvRows;
        }
        $mapping = $data[0];
        fclose($handle);

        return $this->buildToInsertArray($data, $mapping);
    }

    /**
     * @return resource
     *
     * @throws FileException
     */
    private function getFile()
    {
        $this->checkFileIsUsable();

        $handle = $this->isGzipped() ? gzopen($this->fileName, 'r') : fopen($this->fileName, 'r');
        if ($handle === false) {
            throw new FileException('Error while opening file ' . $this->fileName);
        }
        return $handle;
    }

    /**
     * @throws FileException
     */
    private function checkFileIsUsable()
    {
        if (!file_exists($this->fileName)) {
            throw FileNotFoundException::fileNotFound($this->fileName);
        }

        if (!is_readable($this->fileName)) {
            throw FileNotReadableException::fileNotReadable($this->fileName);
        }
    }


    /**
     * Check if the file is gzipped,
     *
     * @return bool
     */
    private function isGzipped()
    {
        // check if file is gzipped
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_mime_type = finfo_file($finfo, $this->fileName);
        finfo_close($finfo);
        $gzipped = strcmp($file_mime_type, "application/x-gzip") == 0;

        return $gzipped;
    }


    /**
     * Build up array to insert into database
     *
     * @param array $csvRows
     * @param array $mapping
     * @return array
     */
    private function buildToInsertArray($csvRows, $mapping)
    {
        $toBuild = [];
        $offset = 1;

        for ($i = $offset; $i < count($csvRows); $i++) {
            $temp = [];
            foreach ($mapping as $key => $value) {
                $temp[$value] = $csvRows[$i][$key];
                // replace empty csv columns with null
                if (strlen($csvRows[$i][$key]) == 0) {
                    $csvRows[$i][$key] = null;
                }
                $temp[$value] = $csvRows[$i][$key];

            }
            $toBuild[] = $temp;
        }

        return $toBuild;
    }

    /**
     * @param $tableName
     */
    public function truncateCascade($tableName)
    {
        $truncateSQL = sprintf('TRUNCATE "%s" RESTART IDENTITY CASCADE;', $tableName);
        $this->execute($truncateSQL);
    }

    /**
     * @param $tableName
     */
    public function insertCsvFromFile($tableName, $isRealData = true)
    {
        // $fileDir = __DIR__ . '/../files/';
        $fileDir = __DIR__ . '/../../../../phinx/db/files/';

        if ($isRealData == true) {
            $fileName = $tableName . '.csv';
        } else {
            $fileName = $tableName . '_fake.csv';
        }

        $fileNameFull = $fileDir . $fileName;
        if (file_exists($fileNameFull)) {
            $this->insertCsv($tableName, $fileNameFull);
        }
    }

    /**
     * @param $tableName
     * @param $checkRealData
     * @return bool
     */
    private function checkCsvFile($tableName, $checkRealData = true)
    {

        $fileDir = __DIR__ . '/../../../../phinx/db/files/';

        if ($checkRealData == true) {
            $fileName = $tableName . '.csv';
        } else {
            $fileName = $tableName . '_fake.csv';
        }
        $fileNameFull = $fileDir . $fileName;

        if (file_exists($fileNameFull)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $tableName
     * @return bool
     */
    public function checkAndImportCSVData($tableName, $importRealAndFake = false)
    {
        $isRealCsvData = $this->checkCsvFile($tableName, true);
        $isFakeCsvData = $this->checkCsvFile($tableName, false);

        if ($importRealAndFake == true) {
            $this->insertCsvFromFile($tableName,true);
            $this->insertCsvFromFile($tableName,false);

            return true;
        }

        if ($isRealCsvData == true) {
            $this->insertCsvFromFile($tableName,true);
            return true;
        }

        if ($isFakeCsvData == true) {
            $this->insertCsvFromFile($tableName,false);
            return true;
        }

        return false;
    }


}