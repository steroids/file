<?php

namespace steroids\file\uploaders;

use steroids\file\structure\UploaderFile;
use yii\base\BaseObject;

abstract class BaseUploader extends BaseObject
{
    /**
     * @param integer|string $size
     * @return integer
     */
    public static function normalizeSize($size)
    {
        $letter = strtoupper(substr($size, -1));
        $size = (int) $size;
        switch ($letter) {
            case 'G':
                $size *= 1024;
            case 'M':
                $size *= 1024;
            case 'K':
                $size *= 1024;
        }
        return (float)$size;
    }

    /**
     * @return UploaderFile[]
     */
    abstract public function upload();
}
