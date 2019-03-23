<?php

namespace OssImportProductStream\Components\SwagImportExport\Service;

use Shopware\Components\SwagImportExport\Service\Struct\PreparationResultStruct;
use Shopware\Components\SwagImportExport\Service as SwagImportExportService;

/**
 * Class ImportService
 * @package OssImportProductStream\Components\SwagImportExport\Service
 * @author  Odessite <alexey.palamar@odessite.com.ua>
 */
class ImportService implements SwagImportExportService\ImportServiceInterface
{
    /**
     * @var SwagImportExportService\ImportService
     */
    private $import;

    /**
     * @var string
     */
    private $fileName;

    /**
     * ExtendedImportService constructor.
     * @param SwagImportExportService\ImportServiceInterface $import
     */
    public function __construct(
        SwagImportExportService\ImportServiceInterface $import
    ) {
        $this->import = $import;
    }
    /**
     * @param array $requestData
     * @param string $inputFileName
     * @return PreparationResultStruct
     * @trows \Exception
     */
    public function prepareImport(array $requestData, $inputFileName)
    {
        return $this->import->prepareImport($requestData, $inputFileName);
    }

    /**
     * @param array $requestData
     * @param array $unprocessedFiles
     * @param string $inputFile
     * @return array
     * @throws \Exception
     */
    public function import(array $requestData, array $unprocessedFiles, $inputFile)
    {
        $this->fileName = str_replace(
            '.'.Shopware()->Container()->get('swag_import_export.upload_path_provider')->getFileExtension($inputFile),
            '',
            Shopware()->Container()->get('swag_import_export.upload_path_provider')->getFileNameFromPath($inputFile)
        );

        $result = $this->import->import($requestData, $unprocessedFiles, $inputFile);

        return $result;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }
}
