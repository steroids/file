<?php

namespace steroids\file\storages;

use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use yii\base\BaseObject;

abstract class BaseStorage extends BaseObject
{
    /**
     * Storage name
     *
     * @var string
     */
    public string $name = '';

    /**
     * @param UploaderFile $file
     * @param string|null $fileFolderToSave Relative folder path to save
     * @return StorageResult
     */
    abstract public function write(UploaderFile $file, $fileFolderToSave = null);

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
     * @param File|FileImage $file
     * @return string
     */
    abstract public function resolveRelativePath($file);

    /**
     * @param File $file
     * @return string
     */
    abstract public function resolveDownloadUrl($file);

    /**
     * @param File $file
     * @return bool
     */
    abstract public function delete($file);
}
