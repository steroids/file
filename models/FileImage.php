<?php

namespace steroids\file\models;

use Exception;
use steroids\core\base\Model;
use steroids\core\behaviors\TimestampBehavior;
use steroids\file\processors\ImageCrop;
use steroids\file\processors\ImageCropResize;
use steroids\file\processors\ImageResize;
use steroids\file\exceptions\FileException;
use steroids\file\FileModule;
use steroids\file\storages\BaseStorage;
use steroids\file\structure\UploaderFile;
use Yii;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;

/**
 * @property integer $id
 * @property integer $fileId
 * @property string $folder
 * @property string $fileName
 * @property string $fileMimeType
 * @property boolean $isOriginal
 * @property integer $width
 * @property integer $height
 * @property string $preview
 * @property integer $createTime
 * @property string $amazoneS3Url
 * @property-read string $path
 * @property-read string $url
 */
class FileImage extends Model
{
    /**
     * @var BaseStorage
     */
    private ?BaseStorage $storage = null;

    /**
     * @throws InvalidConfigException
     * @throws YiiBaseException
     */
    public function init()
    {
        $fileModule = FileModule::getInstance();
        $this->storage = $fileModule->getStorage($fileModule->defaultStorageName);

        parent::init();
    }


    public static function isImageMimeType($value)
    {
        return in_array($value, [
            'image/gif',
            'image/jpeg',
            'image/pjpeg',
            'image/png'
        ]);
    }

    public function fields()
    {
        return [
            'id',
            'width',
            'height',
            'url',
        ];
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%files_images_meta}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * @return string
     */
    public function getRelativePath()
    {
        return $this->storage->resolveRelativePath($this);
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->storage->resolvePath($this);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->storage->resolvePath($this);
    }

    /**
     * @return bool
     * @throws FileException
     * @throws YiiBaseException
     */
    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Delete file
        if (file_exists($this->getPath()) && !unlink($this->getPath())) {
            throw new FileException('Can not remove image thumb file `' . $this->getRelativePath() . '`.');
        }

        return true;
    }

    /**
     * @param string|int $fileId
     * @return static
     */
    public static function findOriginal($fileId)
    {
        return static::findOne([
            'fileId' => $fileId,
            'isOriginal' => true,
        ]);
    }

    /**
     * If $uploaderFile established
     * then field $source will be used
     * for getting path/stream file
     *
     * @param string|int $fileId
     * @param string|array $previewName
     * @param UploaderFile|null $uploaderFile
     * @return FileImage
     * @throws FileException
     * @throws YiiBaseException
     * @throws Exception
     */
    public static function findByPreviewName($fileId, $previewName = FileModule::PREVIEW_DEFAULT, $uploaderFile = null)
    {
        // Check already exists
        /** @var FileImage $previewImage */
        $previewImage = FileImage::findOne([
            'fileId' => $fileId,
            'preview' => $previewName,
        ]);

        if ($previewImage) {
            return $previewImage;
        }

        $previewImage = static::createPreviewImage($fileId, $previewName, $uploaderFile);
        $previewImage->preview = $previewName;
        $previewImage->preview($previewName, $uploaderFile);
        $previewImage->save();

        return $previewImage;
    }

    /**
     * @param string $fileId
     * @param string $previewName
     * @param UploaderFile $uploaderFile
     * @return static
     * @throws Exception
     * @throws FileException
     */
    protected static function createPreviewImage($fileId, $previewName, $uploaderFile = null)
    {
        // Get original image
        $originalImage = static::findOriginal($fileId);
        if (!$originalImage) {
            throw new FileException('Not found original image by id `' . $fileId . '`.');
        }

        // New file meta
        $previewImage = new static();
        $previewImage->fileId = $originalImage->fileId;
        $previewImage->folder = $originalImage->folder;
        $previewImage->fileMimeType = $originalImage->fileMimeType;

        // Generate new file name
        $extension = pathinfo($originalImage->fileName, PATHINFO_EXTENSION);
        $previewExtension = $extension && $extension === 'png' ? 'png' : FileModule::getInstance()->previewExtension;
        $previewImage->fileName = pathinfo($originalImage->fileName, PATHINFO_FILENAME)
            . '.' . $previewName
            . '.' . $previewExtension;

        $destPath = $previewImage->getPath();
        if ($uploaderFile && $uploaderFile->source) {
            $imageRaw = static::getRawData($uploaderFile->source);
        } else {
            $imageRaw = file_get_contents($originalImage->getPath());
        }

        // Create new file
        if (!file_put_contents($destPath, $imageRaw)) {
            throw new FileException('Can not clone original file `'
                . '` to `' . $destPath . '`.'
            );
        }

        return $previewImage;
    }

    /**
     * If $uploaderFile established
     * then field $source will be used
     * for getting path/stream file
     *
     * @param string|array $previewConfig
     * @param UploaderFile|null $uploaderFile
     * @throws FileException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function preview($previewConfig = '', $uploaderFile = null)
    {
        if (!$this->isNewRecord) {

            // Clone from original file
            $originalMeta = static::findOriginal($this->fileId);

            $destPath = $this->getPath();
            if ($uploaderFile && $uploaderFile->source) {
                $imageRaw = static::getRawData($uploaderFile->source);
            } else {
                $imageRaw = file_get_contents($originalMeta->getPath());
            }

            if (!unlink($this->getPath()) || !file_put_contents($destPath, $imageRaw)) {
                throw new FileException('Can not re-create image meta file from original `'
                    . '` to `' . $destPath . '`.'
                );
            }
        }

        if (is_string($previewConfig)) {
            $previews = FileModule::getInstance()->previews;
            if (!isset($previews[$previewConfig])) {
                throw new FileException('Not found preview by name `' . $previewConfig . '`');
            }
            $previewConfig = $previews[$previewConfig];
        }

        /** @var ImageCrop|ImageCropResize|ImageResize $preview */
        $preview = Yii::createObject($previewConfig);
        $preview->filePath = $this->storage->resolvePath($this);

        $preview->previewQuality = (int)FileModule::getInstance()->previewQuality;
        $preview->run();

        if (isset($previewConfig['width']) && isset($previewConfig['height'])) {
            $this->width = $preview->width;
            $this->height = $preview->height;
        }
    }

    /**
     * @param integer $width
     * @param integer $height
     */
    public function checkFixedSize($width, $height)
    {
        if (!$width || !$height) {
            $this->addError('id',
                Yii::t('steroids', 'Fixed height or width must be greater than 0')
            );
            return;
        }

        if ($this->width < $width && $this->height < $height) {
            $this->addError('id',
                Yii::t('steroids', 'Image is smaller that the given fixed size')
            );
        }

        if ((int)floor($this->width / $this->height) !== (int)floor($width / $height)) {
            $this->addError('id',
                Yii::t('steroids', 'Image has different height/width ratio than the given size')
            );
        }
    }

    /**
     * @param resource|string $source
     * @return string
     */
    private static function getRawData($source)
    {
        if (is_resource($source)) {
            return stream_get_contents($source);
        } else {
            return file_get_contents($source);
        }
    }

}
