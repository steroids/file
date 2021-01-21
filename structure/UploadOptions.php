<?php

namespace steroids\file\structure;

use yii\base\BaseObject;

/**
 * Class UploadOptions
 */
class UploadOptions extends BaseObject
{
    /**
     * Relative path to sub-folder
     * @var string|null
     */
    public ?string $folder = null;

    /**
     * Path to source file or UploaderFile instance
     * @var UploaderFile|string|resource|null
     */
    public $source = null;

    /**
     * Uploader name (post, put)
     * @var string|null
     */
    public ?string $uploaderName = null;

    /**
     * Storage name (file, aws, ...)
     * @var string|null
     */
    public ?string $storageName = null;

    /**
     * Max file size in megabyte
     * @var int|null
     */
    public ?int $maxSizeMb = null;

    /**
     * Set file mime types list for check
     * @var string[]|null
     */
    public ?array $mimeTypes = null;

    /**
     * Set true, for auto fill mime types as images (gif, jpeg, pjpeg, png)
     * @see imagesMimeTypes() method
     * @var bool|null
     */
    public ?bool $imagesOnly = false;

    /**
     * Default mime types for images
     * @return string[]
     */
    public static function imagesMimeTypes(): array
    {
        return [
            'image/gif',
            'image/jpeg',
            'image/pjpeg',
            'image/png'
        ];
    }
}
