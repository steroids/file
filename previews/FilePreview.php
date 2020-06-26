<?php

namespace steroids\file\previews;

use steroids\file\exceptions\FileException;
use yii\base\BaseObject;

abstract class FilePreview extends BaseObject
{
    public string $filePath = '';

    public int $previewQuality = 90;

    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @throws FileException
     */
    abstract public function run();
}