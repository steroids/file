<?php

namespace steroids\file\uploaders;

use steroids\file\models\File;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use yii\base\BaseObject;

abstract class BaseStorage extends BaseObject
{
    /**
     * @param UploaderFile $file
     * @param string|null $folder Relative folder path to save
     * @return StorageResult
     */
    abstract public function write(UploaderFile $file, $folder = null);

    /**
     * @param File $file
     * @return string
     */
    abstract public function resolvePath(File $file);

    /**
     * @param File $file
     * @return string
     */
    abstract public function resolveUrl(File $file);
}
