<?php

namespace steroids\file;

use Exception;
use steroids\core\base\Module;
use steroids\file\events\UploadAfterEvent;
use steroids\file\events\UploadEvent;
use steroids\file\exceptions\FileUserException;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\previews\ImageFitWithCrop;
use steroids\file\previews\ImageResize;
use steroids\file\storages\AwsStorage;
use steroids\file\storages\BaseStorage;
use steroids\file\storages\FileStorage;
use steroids\file\structure\UploaderFile;
use steroids\file\structure\UploadOptions;
use steroids\file\uploaders\BaseUploader;
use steroids\file\uploaders\PostUploader;
use steroids\file\uploaders\PutUploader;
use Yii;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;
use yii\web\Response;

class FileModule extends Module
{
    const EVENT_BEFORE_UPLOAD = 'beforeUpload';
    const EVENT_AFTER_UPLOAD = 'afterUpload';

    const STORAGE_FILE = 'file';
    const STORAGE_AWS = 'aws';

    const UPLOADER_POST = 'post';
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
     * @var array
     */
    public const MIMETYPE_EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/svg+xml' => 'svg',
        'image/tiff' => 'tif'
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
     * @var array
     */
    public array $classesMap = [];

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        $this->uploaders = ArrayHelper::merge(
            [
                self::UPLOADER_PUT => [
                    'class' => PutUploader::class
                ],
                self::UPLOADER_POST => [
                    'class' => PostUploader::class
                ],
            ],
            $this->uploaders
        );
        $this->storages = ArrayHelper::merge(
            [
                'file' => [
                    'class' => FileStorage::class,
                ],
            ],
            $this->storages
        );

        $this->previews = ArrayHelper::merge(
            [
                self::PREVIEW_ORIGINAL => [
                    'class' => ImageResize::class,
                    'width' => 1920,
                    'height' => 1200,
                ],
                self::PREVIEW_DEFAULT => [
                    'class' => ImageFitWithCrop::class,
                    'width' => 200,
                    'height' => 200,
                ],
                self::PREVIEW_THUMBNAIL => [
                    'class' => ImageFitWithCrop::class,
                    'width' => 500,
                    'height' => 300,
                ],
                self::PREVIEW_FULLSCREEN => [
                    'class' => ImageFitWithCrop::class,
                    'width' => 1600,
                    'height' => 1200,
                ],
            ],
            $this->previews
        );

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

        $this->classesMap = array_merge([
            'steroids\file\models\File' => File::class,
            'steroids\file\models\FileImage' => FileImage::class,
        ], $this->classesMap);
    }

    /**
     * @param UploadOptions|array|string $options
     * @return File
     * @throws FileUserException
     * @throws InvalidConfigException
     * @throws YiiBaseException
     * @throws exceptions\FileException
     */
    public function upload($options = [])
    {
        // Normalize format
        if (is_string($options)) {
            $options = new UploadOptions(['source' => $options]);
        }
        if (is_array($options)) {
            $options = new UploadOptions($options);
        }

        // No file - upload from request
        if (!$options->source) {
            // Auto detect upload method
            if (!$options->uploaderName) {
                $options->uploaderName = empty($_FILES) ? self::UPLOADER_PUT : self::UPLOADER_POST;
            }

            // Create source from POST/PUT request
            $options->source = $this->getUploader($options->uploaderName)->upload();

            // Set response as json on exceptions
            if (Yii::$app->response instanceof Response) {
                Yii::$app->response->format = Response::FORMAT_JSON;
            }
        }

        // Source as path to file
        if (is_string($options->source) || is_resource($options->source)) {
            $options->source = new UploaderFile([
                'source' => $options->source,
                'name' => is_string($options->source)
                    ? preg_replace('/[^A-Za-zА-Яа-я0-9_.-]/', '', StringHelper::basename($options->source))
                    : null,
            ]);
        }

        // Auto detect source file size
        if (!$options->source->size) {
            if (is_string($options->source->source)) {
                if (strpos($options->source->source, '://') !== false) {
                    $options->source->size = $this->retrieveRemoteFileSize($options->source->source);
                } else {
                    $options->source->size = filesize($options->source->source);
                }
            } elseif (is_resource($options->source->source)) {
                $options->source->size = fstat($options->source->source)['size'];
            }
        }

        // Auto detect source file mime type by file extension
        if (!$options->source->mimeType) {
            $options->source->mimeType = FileHelper::getMimeTypeByExtension($options->source->name);
        }

        // Default storage name
        if (!$options->storageName) {
            $options->storageName = $this->defaultStorageName;
        }

        // Normalize folder
        if ($options->folder) {
            $options->folder = FileHelper::normalizePath($options->folder);
        }

        // Normalize mime types for image
        if ($options->imagesOnly && !$options->mimeTypes) {
            $options->mimeTypes = UploadOptions::imagesMimeTypes();
        }

        // Run before event
        if (!$this->beforeUpload($options)) {
            return null;
        }

        // Validate size
        if ($options->source->size) {
            // Project size limit
            if ($options->maxSizeMb && $options->source->size > $options->maxSizeMb * 1024 * 1024) {
                throw new FileUserException(\Yii::t('steroids', 'Файл слишком большой, загрузите файл не более {size} Mb', [
                    'size' => $options->maxSizeMb,
                ]));
            }

            // Server size limit
            $serverMaxSize = min(
                BaseUploader::normalizeSize(ini_get('upload_max_filesize')),
                BaseUploader::normalizeSize(ini_get('post_max_size'))
            );
            if ($serverMaxSize && $options->source->size > $serverMaxSize) {
                throw new FileUserException(\Yii::t('steroids', 'Конфигурация сервера позволяет загрузить файл не более {size} Mb', [
                    'size' => round(($serverMaxSize / 1024) / 1024)
                ]));
            }
        }

        // Validate mime types
        if (!empty($options->mimeTypes) && $options->source->mimeType
            && !in_array($options->source->mimeType, $options->mimeTypes)
        ) {
            throw new FileUserException(\Yii::t('steroids', 'Неверный формат файла'));
        }

        // TODO Check fix image size
        /*if (!empty($fileConfig['fixedSize']) && !$file->checkImageFixedSize($fileConfig['fixedSize'])) {
            return [
                'errors' => $file->getImageMeta(static::PROCESSOR_NAME_ORIGINAL)->getFirstErrors()
            ];
        }*/

        // Create model
        $file = new File([
            'uid' => $options->source->uid,
            'title' => $options->source->title,
            'folder' => $options->folder,
            'fileName' => $options->source->savedFileName,
            'fileSize' => $options->source->size,
            'storageName' => $options->storageName,
            'userId' => Yii::$app->has('user') ? Yii::$app->user->getId() : null,
        ]);

        // Save to storage
        $storage = $this->getStorage($options->storageName);
        $storageResult = $storage->write($options->source, $options->folder);

        // Refresh real attributes
        $file->attributes = $storageResult->getAttributes();
        $file->fileMimeType = $options->source->mimeType;

        // Already check mime type, if it changed after real upload (and checked by file headers)
        if ($options->source->mimeType !== $file->fileMimeType && !empty($options->mimeTypes)
            && $options->source->mimeType && !in_array($options->source->mimeType, $options->mimeTypes)
        ) {
            throw new FileUserException(\Yii::t('steroids', 'Неверный формат файла'));
        }

        // Save model
        if (!$file->save()) {
            throw new FileUserException(\Yii::t('steroids', 'Ошибка при сохранении: {errors}', [
                'errors' => implode(', ', array_values($file->getFirstErrors())),
            ]));
        }

        // Create image previews
        if (!$this->previewLazyCreate && $file->isImage()) {
            foreach (array_keys($this->previews) as $previewName) {
                $file->getImagePreview($previewName, $options->source);
            }
        }

        // Run after event
        $this->afterUpload($options, $file);

        return $file;
    }

    /**
     * @param UploadOptions $options
     * @return bool|null
     */
    public function beforeUpload(UploadOptions $options): ?bool
    {
        $event = new UploadEvent(['options' => $options]);
        $this->trigger(self::EVENT_BEFORE_UPLOAD, $event);

        return $event->isValid;
    }

    /**
     * @param UploadOptions $options
     * @param File $file
     */
    public function afterUpload(UploadOptions $options, File $file)
    {
        $this->trigger(self::EVENT_AFTER_UPLOAD, new UploadAfterEvent([
            'options' => $options,
            'file' => $file,
        ]));
    }

    /**
     * @param string $folder
     * @param string $uploaderName
     * @param string $storageName
     * @return File[]
     * @throws InvalidConfigException
     * @deprecated Use upload() method
     */
    public function uploadFromRequest($folder = null, $uploaderName = null, $storageName = null)
    {
        return [
            $this->upload(new UploadOptions([
                'folder' => $folder,
                'uploaderName' => $uploaderName,
                'storageName' => $storageName,
            ]))
        ];
    }

    /**
     * @param UploaderFile|string|resource $source
     * @param string $folder
     * @param string|null $storageName
     * @return File
     * @throws YiiBaseException
     * @throws Exception
     * @deprecated Use upload() method
     */
    public function uploadFromFile($source, $folder = null, $storageName = null)
    {
        return $this->upload(new UploadOptions([
            'source' => $source,
            'folder' => $folder,
            'storageName' => $storageName,
        ]));
    }

    /**
     * @param string $name
     * @return BaseStorage|null
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

    protected function retrieveRemoteFileSize($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE);

        $data = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);
        return $size;
    }
}
