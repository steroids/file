<?php

namespace steroids\file\models;

use Exception;
use steroids\core\base\Model;
use steroids\core\behaviors\TimestampBehavior;
use steroids\file\previews\ImageCrop;
use steroids\file\previews\ImageCropResize;
use steroids\file\previews\ImageResize;
use steroids\file\exceptions\FileException;
use steroids\file\FileModule;
use steroids\file\storages\BaseStorage;
use steroids\file\structure\UploaderFile;
use Yii;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;

/**
 * @property integer $id
 * @property integer $fileId
 * @property string $folder
 * @property string $fileName
 * @property string $fileMimeType
 * @property boolean $isOriginal
 * @property integer $width
 * @property integer $height
 * @property string $previewName
 * @property integer $createTime
 * @property string $amazoneS3Url
 * @property-read string $path
 * @property-read string $url
 *
 * @property-read File $file
 * @property-read BaseStorage $storage
 */
class FileImage extends Model
{
    /**
     * @var BaseStorage
     */
    private ?BaseStorage $_storage = null;

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

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Delete file
        $this->storage->deleteImageMeta($this);
        return true;
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'file_images';
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
     * @return ActiveQuery
     */
    public function getFile()
    {
        return $this->hasOne(File::class, ['id' => 'fileId']);
    }

    /**
     * @return BaseStorage
     * @throws InvalidConfigException
     * @throws YiiBaseException
     */
    public function getStorage()
    {
        if (!$this->_storage) {
            $fileModule = FileModule::getInstance();
            return $this->_storage = $fileModule->getStorage($this->file->storageName);
        }
        return $this->_storage;
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
        return $this->storage->resolveUrl($this);
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
            'previewName' => $previewName,
        ]);

        if ($previewImage) {
            return $previewImage;
        }

        $previewImage = static::createPreviewImage($fileId, $previewName);
        $previewImage->previewName = $previewName;
        $previewImage->preview($previewName, $uploaderFile);
        $previewImage->save();

        return $previewImage;
    }

    /**
     * @param string $fileId
     * @param string $previewName
     * @return static
     * @throws Exception
     * @throws FileException
     */
    protected static function createPreviewImage($fileId, $previewName)
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

        $previewImage->savePreviewImage();

        return $previewImage;
    }

    private function savePreviewImage()
    {
        // Get stream to file
        $imgResource = $this->storage->read($this->file);

        // Create new uploader file and configure it for storage
        $previewUploaderFile = new UploaderFile();
        $fileName = implode(DIRECTORY_SEPARATOR, array_filter([
            $this->folder !== DIRECTORY_SEPARATOR ? $this->folder : null,
            $this->fileName,
        ]));

        $fileNameWithoutExtension = pathinfo($this->fileName, PATHINFO_FILENAME);

        $previewUploaderFile->name = $fileName;
        $previewUploaderFile->mimeType = $this->fileMimeType;
        $previewUploaderFile->source = $imgResource;
        $previewUploaderFile->setUid($fileNameWithoutExtension);

        // Store
        $this->storage->write($previewUploaderFile, $this->folder);
    }

    /**
     * @param string|array $previewConfig
     * @param UploaderFile|null $uploaderFile
     * @throws FileException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function preview($previewConfig = '', $uploaderFile = null)
    {
        if (!$this->isNewRecord) {
            $this->storage->deleteImageMeta($this);
            $this->storage->write($uploaderFile);
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
        $preview->source = $this->storage->read($this);

        $extension = pathinfo($this->fileName, PATHINFO_EXTENSION);
        $previewExtension = $extension && $extension === 'png' ? 'png' : (string)FileModule::getInstance()->previewExtension;

        $preview->previewExtension = $previewExtension;
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
}
