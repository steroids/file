<?php

namespace steroids\file;

use Exception;
use Yii;
use steroids\file\exceptions\FileUserException;
use steroids\file\storages\AwsStorage;
use steroids\file\storages\FileStorage;
use steroids\core\base\Module;
use steroids\file\models\File;
use steroids\file\structure\UploaderFile;
use steroids\file\storages\Storage;
use steroids\file\uploaders\BaseUploader;
use steroids\file\uploaders\PostUploader;
use steroids\file\uploaders\PutUploader;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

class FileModule extends Module
{
    const STORAGE_FILE = 'file';
    const STORAGE_AWS = 'aws';

    const UPLOADER_POST = 'POST';
    const UPLOADER_PUT = 'put';

    const PREVIEW_ORIGINAL = 'original';
    const PREVIEW_DEFAULT = 'default';
    const PREVIEW_THUMBNAIL = 'thumbnail';
    const PREVIEW_FULLSCREEN = 'fullscreen';

    public string $defaultStorageName = self::STORAGE_FILE;

    /**
     * @var array
     */
    public array $storages = [];

    /**
     *
     * @var array
     */
    public array $storagesClasses = [
        self::STORAGE_FILE => FileStorage::class,
        self::STORAGE_AWS => AwsStorage::class,
    ];

    /**
     * @var array
     */
    public array $uploaders = [];

    /**
     *
     * @var array
     */
    public array $uploadersClasses = [
        self::UPLOADER_POST => PostUploader::class,
        self::UPLOADER_PUT => PutUploader::class,
    ];

    /**
     * @var array
     */
    public array $previews = [];

    /**
     * Default image previews (used when ImageMeta export)
     * @var array
     */
    public array $previewPublished = [
        self::PREVIEW_DEFAULT,
        self::PREVIEW_THUMBNAIL,
        self::PREVIEW_FULLSCREEN,
    ];

    /**
     * Format is jpg or png
     * @var string
     */
    public string $previewExtension = 'jpg';

    /**
     * From 0 to 100 percents
     * @var int
     */
    public int $previewQuality = 90;

    /**
     * Create image preview only on generate links
     * @var bool
     */
    public bool $previewLazyCreate = false;

    /**
     * The name of the x-sendfile header
     * @var string
     */
    public $xHeader = false;

    /**
     * Maximum file size limit
     * @var string
     */
    public string $fileMaxSize = '200M';

    /**
     * Absolute url to file icons directory (if exists)
     * @var string
     */
    public string $iconsRootUrl = '';

    /**
     * Absolute path to file icons directory (if exists)
     * @var string
     */
    public string $iconsRootPath = '';

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->previews = ArrayHelper::merge($this->defaultPreviews(), $this->previews);

        if ($this->iconsRootUrl) {
            $this->iconsRootUrl = Yii::getAlias($this->iconsRootUrl);
        }
        if ($this->iconsRootPath) {
            $this->iconsRootPath = Yii::getAlias($this->iconsRootPath);
        }

        $this->fileMaxSize = min(
            BaseUploader::normalizeSize($this->fileMaxSize),
            BaseUploader::normalizeSize(ini_get('upload_max_filesize')),
            BaseUploader::normalizeSize(ini_get('post_max_size'))
        );
    }

    /**
     * @param string $folder
     * @param string $uploaderName
     * @param string $storageName
     * @return File[]
     * @throws InvalidConfigException
     */
    public function uploadFromRequest($folder = null, $uploaderName = null, $storageName = null)
    {
        if (!$uploaderName) {
            $uploaderName = empty($_FILES) ? self::UPLOADER_PUT : self::UPLOADER_POST;
        }

        return array_map(
            fn($file) => $this->uploadFromFile($file, $folder, $storageName),
            $this->getUploader($uploaderName)->upload()
        );
    }

    /**
     * @param UploaderFile|string|resource $uploaderFile
     * @param string $folder
     * @param string|null $storageName
     * @return File
     * @throws YiiBaseException
     * @throws Exception
     */
    public function uploadFromFile($uploaderFile, $folder = null, $storageName = null)
    {
        // Single format for arguments
        if (!is_object($uploaderFile)) {
            $uploaderFile = new UploaderFile(['source' => $uploaderFile]);
        }

        $storageName = $storageName ?: $this->defaultStorageName;

        // Normalize folder
        if ($folder) {
            $folder = FileHelper::normalizePath($folder);
        }

        // Create model
        $file = new File([
            'uid' => $uploaderFile->uid,
            'title' => $uploaderFile->title,
            'folder' => $folder,
            'fileName' => $uploaderFile->savedFileName,
            'fileSize' => $uploaderFile->size,
            'storageName' => $storageName,
            'userId' => Yii::$app->has('user') ? Yii::$app->user->getId() : null,
        ]);

        // Save to storage
        $storage = $this->getStorage($storageName);
        $storageResult = $storage->write($uploaderFile, $folder);

        $file->attributes = $storageResult->getAttributes();
        $file->fileMimeType = $uploaderFile->mimeType;

        // Save model
        if (!$file->save()) {
            throw new FileUserException(implode(', ',  array_values($file->getFirstErrors())));
        }

        // TODO Check file size
        /*if ($file->size > $this->maxFileSize) {
            $this->addError('files', \Yii::t('steroids', 'The uploaded file is too large. Max size: {size} Mb', [
                'size' => round(($this->maxFileSize / 1024) / 1024),
            ]));
            return false;
        }*/
        /*if ($file->size && $file->size > $this->maxFileSize) {
            $this->addError('maxFileSize', \Yii::t('steroids', 'The uploaded file is too large. Max size: {size} Mb', [
                'size' => round(($this->maxFileSize / 1024) / 1024),
            ]));
            return false;
        }*/
        /*if ($file->size && $file->size > $this->maxRequestSize) {
            $this->addError('maxRequestSize', \Yii::t('steroids', 'Summary uploaded files size is too large. Available size: {size} Mb', [
                'size' => round(($this->maxRequestSize / 1024) / 1024),
            ]));
            return false;
        }*/
        /*$summaryFilesSize = array_sum(array_map(fn (UploaderFile $file) => $file->size, $this->files));
        if ($summaryFilesSize > $this->maxRequestSize) {
            $this->addError('maxRequestSize', \Yii::t('steroids', 'Summary uploaded files size is too large. Available size: {size} Mb', [
                'size' => round(($this->maxRequestSize / 1024) / 1024),
            ]));
            return false;
        }*/

        // TODO Check file mime type format
        //if (is_array($this->mimeTypes) && !in_array(static::getFileMimeType($file->rawData['tmp_name']), $this->mimeTypes)) {
            //throw new FileUserException(\Yii::t('steroids', 'Incorrect file format.'));
        //}
        // Check mime type from header
        /*if (is_array($this->mimeTypes) && !in_array($file->mimeType, $this->mimeTypes)) {
            $this->addError('files', \Yii::t('steroids', 'Incorrect file format.'));
            return false;
        }*/

        // TODO Check fix image size
        /*if (!empty($fileConfig['fixedSize']) && !$file->checkImageFixedSize($fileConfig['fixedSize'])) {
            return [
                'errors' => $file->getImageMeta(static::PROCESSOR_NAME_ORIGINAL)->getFirstErrors()
            ];
        }*/

        // Create image previews
        if (!$this->previewLazyCreate && $file->isImage()) {
            foreach (array_keys($this->previews) as $previewName) {
                $file->getImagePreview($previewName, $uploaderFile);
            }
        }

        return $file;
    }

    /**
     * @param string $name
     * @return Storage|null
     * @throws InvalidConfigException
     */
    public function getStorage($name)
    {
        if (!$this->storages || !isset($this->storages[$name])) {
            return null;
        }
        if (is_array($this->storages[$name])) {
            $this->storages[$name] = Yii::createObject(array_merge(
                ['class' => ArrayHelper::getValue($this->storagesClasses, $name)],
                $this->storages[$name],
                ['name' => $name]
            ));
        }
        return $this->storages[$name];
    }

    /**
     * @param string $name
     * @return BaseUploader|null
     * @throws InvalidConfigException
     */
    public function getUploader($name)
    {
        if (!$this->uploaders || !isset($this->uploaders[$name])) {
            return null;
        }
        if (is_array($this->uploaders[$name])) {
            $this->uploaders[$name] = Yii::createObject(array_merge(
                ['class' => ArrayHelper::getValue($this->uploadersClasses, $name)],
                $this->uploaders[$name],
                ['name' => $name]
            ));
        }
        return $this->uploaders[$name];
    }

    /**
     * @return array
     */
    protected function defaultPreviews()
    {
        return [
            self::PREVIEW_ORIGINAL => [
                'width' => 1920,
                'height' => 1200,
            ],
            self::PREVIEW_DEFAULT => [
                'width' => 200,
                'height' => 200,
                'crop' => true,
            ],
            self::PREVIEW_THUMBNAIL => [
                'width' => 500,
                'height' => 300,
                'crop' => true,
            ],
            self::PREVIEW_FULLSCREEN => [
                'width' => 1600,
                'height' => 1200,
            ],
        ];
    }
}
