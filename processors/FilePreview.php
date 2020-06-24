<?php

namespace steroids\file\processors;

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
    public function run()
    {
        if (!file_exists($this->filePath)) {
            throw new FileException('Not found file `' . $this->filePath . '`');
        }

        $this->runInternal();
    }

    abstract protected function runInternal();
}