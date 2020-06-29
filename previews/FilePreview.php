<?php

namespace steroids\file\previews;

use steroids\file\exceptions\FileException;
use yii\base\BaseObject;

abstract class FilePreview extends BaseObject
{
    /**
     * @var resource
     */
    public $source;

    public int $previewQuality = 90;

    public string $previewExtension = 'jpg';

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