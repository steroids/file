<?php

namespace steroids\file\storages;

use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use yii\base\BaseObject;

abstract class Storage extends BaseObject
{
    /**
     * Storage name
     *
     * @var string
     */
    public string $name = '';

    /**
     * @param UploaderFile $file
     * @param string|null $folder Relative folder path to save
     * @return StorageResult
     */
    abstract public function write(UploaderFile $file, $folder = null);

    /**
     * @param UploaderFile $file
     * @return resource
     */
    abstract public function read(UploaderFile $file);

    /**
     * @param File $file
     * @return void
     */
    abstract public function delete($file);

    /**
     * @param File|FileImage $file
     * @return string
     */
    abstract public function resolvePath($file);

    /**
     * @param File|FileImage $file
     * @return string
     */
    abstract public function resolveUrl($file);

    /**
     * @param File $file
     * @return string
     */
    abstract public function resolveDownloadUrl($file);

    /**
     * @param File|FileImage $file
     * @param string|null $root
     * @return string
     */
    protected function getFullFileName($file, $root = null)
    {
        return implode(DIRECTORY_SEPARATOR, array_filter([
            $root,
            $file->folder !== DIRECTORY_SEPARATOR ? $file->folder : null,
            $file->fileName
        ]));
    }
}
