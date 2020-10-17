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

    public $SKIP_VAR_NAME = 'SKIP_FAKE';

    private $fileBasePath = __DIR__ . '/../../../..';

    /**
     * @return mixed
     */
    public function getFileBasePath()
    {
        return $this->fileBasePath;
    }

    /**
     * @param mixed $fileBasePath
     */
    public function setFileBasePath($fileBasePath): void
    {
        $this->fileBasePath = $fileBasePath;
    }

    private $table_name;

    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * @param mixed $table_name
     */
    public function setTableName($table_name): void
    {
        $this->table_name = $table_name;
    }

    protected function configureMigration()
    {

        // to be override
    }

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
        $fileDir =  $this->fileBasePath.'/phinx/db/files/';

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

        //$fileDir = __DIR__ . '/../../../../phinx/db/files/';
        $fileDir =  $this->fileBasePath.'/phinx/db/files/';

        if ($checkRealData == true) {
            $fileName = $tableName . '.csv';
        } else {
            $fileName = $tableName . '_fake.csv';
        }
        $fileNameFull = $fileDir . $fileName;

        $fileExist =file_exists($fileNameFull);
        return $fileExist;

    }

    /**
     * @param $tableName
     * @return bool
     */
    public function checkAndImportCSVData($tableName, $importRealAndFake = false)
    {
        $dataImported = false;

        $dataImported = $this->performImportRealData($tableName);

        $skipFakeData = $this->getSkipFakeDataFlag();

        if ($skipFakeData != true) {
            $dataImported = $this->performImportFakeData($tableName) || $dataImported;
        }

        return $dataImported;
    }

    protected function getSkipFakeDataFlag()
    {
        $skipFlag = false;
        $skipFlagEnvValue = getenv($this->SKIP_VAR_NAME);

        if (isset($skipFlagEnvValue)) {
            $skipFlag = ($skipFlagEnvValue == '1') || ($skipFlagEnvValue == 'true') || ($skipFlagEnvValue == 'on');
        }

        return $skipFlag;
    }

    protected function performImportRealData($tableName)
    {
        $isRealCsvData = $this->checkCsvFile($tableName, true);
        if ($isRealCsvData == true) {
            $this->insertCsvFromFile($tableName, true);
        } else {
            $this->doManualImportReal($tableName);
        }
        return false;

    }

    protected function doManualImportReal($tableName)
    {

    }

    protected function doManualImportFake($tableName)
    {

    }

    protected function performImportFakeData($tableName)
    {
        $isFakeCsvData = $this->checkCsvFile($tableName, false);

        if ($isFakeCsvData == true) {
            $this->insertCsvFromFile($tableName, false);
        } else {
            $this->doManualImportFake($tableName);
        }

        return false;
    }

    public function up()
    {
        $this->configureMigration();

        $isImportedData = $this->checkAndImportCSVData($this->getTableName(), false);
    }

    public function down()
    {
        $this->truncateCascade($this->getTableName());
    }


}