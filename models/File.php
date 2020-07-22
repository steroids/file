<?php

namespace steroids\file\models;

use steroids\core\base\Model;
use steroids\core\behaviors\TimestampBehavior;
use steroids\core\behaviors\UidBehavior;
use steroids\core\exceptions\ModelSaveException;
use steroids\file\exceptions\FileException;
use steroids\file\FileModule;
use steroids\file\storages\BaseStorage;
use steroids\file\structure\Photo;
use steroids\file\structure\UploaderFile;
use Throwable;
use yii\base\Exception as YiiBaseException;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

/**
 * @property integer $id
 * @property string $uid
 * @property string $title
 * @property string $folder
 * @property string $fileName
 * @property string $fileMimeType
 * @property string $fileSize
 * @property integer $createTime
 * @property boolean $isTemp
 * @property string $storageName
 * @property string $amazoneS3Url
 * @property-read string $path
 * @property-read string $url
 * @property-read string $downloadUrl
 * @property-read string $downloadName
 *
 * @property string $md5
 * @property integer $userId
 * @property-read BaseStorage $storage
 */
class File extends Model
{
    public array $previews = [
        FileModule::PREVIEW_DEFAULT
    ];

    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%files}}';
    }

    /**
     * @var BaseStorage|null
     */
    private ?BaseStorage $_storage = null;

    /**
     * @return BaseStorage
     * @throws InvalidConfigException
     * @throws YiiBaseException
     */
    public function getStorage()
    {
        if (!$this->_storage) {
            $fileModule = FileModule::getInstance();
            return $this->_storage = $fileModule->getStorage($fileModule->defaultStorageName);
        }
        return $this->_storage;
    }

    /**
     * @param static|static[] $file
     * @param string|string[] $previews
     * @return array|null
     * @throws YiiBaseException
     */
    public static function asPhotos($file, $previews = null)
    {
        $previews = $previews ?: FileModule::getInstance()->previews;

        if (is_array($file)) {
            return array_map(function ($model) use ($previews) {
                return static::asPhotos($model, $previews);
            }, $file);
        } elseif ($file) {
            $result = [];
            foreach ((array)$previews as $previewName) {
                try {
                    $imageMeta = $file->getImagePreview($previewName);
                } catch (FileException $e) {
                    return null;
                }
                $result[$previewName] = new Photo($imageMeta->toFrontend([
                    'url',
                    'width',
                    'height',
                ]));
            }
            return $result;
        }
        return null;
    }

    /**
     * @param string $url
     * @return static
     */
    public static function findByUrl($url)
    {
        // Find uid
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/', $url, $match)) {
            return static::findOne(['uid' => $match[0]]);
        }
        return null;
    }

    /**
     * @param File[]|File $models
     * @param string[] $previews
     * @return File[]|File
     */
    public static function prepareProcessors($models, $previews)
    {
        if (is_array($models)) {
            $models = array_map(function ($model) use ($previews) {
                $model->previews = $previews;
                return $model;
            }, $models);
        } elseif ($models instanceof File) {
            $models->previews = $previews;
        }
        return $models;
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            UidBehavior::class,
            TimestampBehavior::class,
        ];
    }

    public function fields()
    {
        return [
            'id',
            'uid',
            'title',
            'folder',
            'fileName',
            'fileMimeType',
            'fileSize' => function(File $model) { // fix call filesize() on toFrontend()
                return $model->fileSize;
            },
            'createTime',
            'url',
            'downloadUrl',
            'images',
            'md5' => function(File $model) { // fix call php md5() function on toFrontend()
                return $model->md5;
            },
            'userId'
        ];
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            ['isTemp', 'boolean'],
            ['title', 'filter', 'filter' => function ($value) {
                return preg_replace('/^[^\\\\\/]*[\\\\\/]/', '', $value);
            }],
            ['title', 'string', 'max' => 255],
            ['folder', 'match', 'pattern' => '/^[a-z0-9+-_\/.]+$/i'],
            ['folder', 'filter', 'filter' => function ($value) {
                return rtrim($value, '/') . '/';
            }],
            ['fileName', 'string'],
            ['fileSize', 'integer'],
            ['fileMimeType', 'default', 'value' => 'text/plain'],
            ['md5', 'string', 'max' => 255],
            ['userId', 'number'],
            ['uid', 'default', 'value' => UidBehavior::generate()],
        ];
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

    public function getDownloadName()
    {
        $ext = '.' . pathinfo($this->fileName, PATHINFO_EXTENSION);
        return $this->title . (substr($this->title, -4) !== $ext ? $ext : '');
    }

    /**
     * @return string
     */
    public function getDownloadUrl()
    {
        return Url::to([
            '/file/download/index',
            'uid' => $this->uid,
            'name' => $this->getDownloadName()
        ], true);
    }

    /*public function getIconName()
    {
        $ext = pathinfo($this->fileName, PATHINFO_EXTENSION);
        $ext = preg_replace('/[^0-9a-z_]/', '', $ext);
        $iconPath = __DIR__ . '/../../../client/images/fileIcons/' . $ext . '.png';

        return file_exists($iconPath) ? $ext : 'default';
    }*/

    /**
     * @return bool
     * @throws Throwable
     */
    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->storage->delete($this);
        return true;
    }

    /**
     * If $uploaderFile established
     * then field $source will be used
     * for getting path/stream file
     *
     * @param string $previewName
     * @param UploaderFile|null $uploaderFile
     * @return FileImage
     * @throws FileException
     * @throws YiiBaseException
     */
    public function getImagePreview($previewName = FileModule::PREVIEW_DEFAULT, $uploaderFile = null)
    {
        if (!isset($this->previews[$previewName])) {
            $this->previews[$previewName] = FileImage::findByPreviewName($this->id, $previewName, $uploaderFile);
        }
        return $this->previews[$previewName];
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @throws FileException
     * @throws YiiBaseException
     * @throws ModelSaveException
     * @throws InvalidConfigException
     */
    public function afterSave($insert, $changedAttributes)
    {
        // Create ImageMeta for images
        if ($insert && $this->isImage()) {

            // Create instance
            $imageMeta = new FileImage([
                'fileId' => $this->id,
                'folder' => $this->folder,
                'fileName' => $this->fileName,
                'fileMimeType' => $this->fileMimeType,
                'isOriginal' => true,
                'previewName' => FileModule::PREVIEW_ORIGINAL,
            ]);

            // Save
            $imageMeta->preview(FileModule::PREVIEW_ORIGINAL);
            $imageMeta->saveOrPanic();
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return array
     * @throws FileException
     * @throws YiiBaseException
     */
    public function getImages()
    {
        $images = [];
        if ($this->isImage()) {
            foreach ($this->previews as $previewName => $preview) {
                if (is_string($preview)) {
                    $images[$preview] = $this->getImagePreview($preview);
                } else {
                    $images[$previewName] = $preview;
                }
            }
        } elseif (in_array(FileModule::PREVIEW_DEFAULT, $this->previews)) {
            $iconsPath = FileModule::getInstance()->iconsRootPath;
            $iconsUrl = FileModule::getInstance()->iconsRootUrl;
            if ($iconsPath && $iconsUrl) {
                $iconName = pathinfo($this->fileName, PATHINFO_EXTENSION) . '.png';
                $images[FileModule::PREVIEW_DEFAULT] = [
                    'url' => file_exists($iconsPath . '/' . $iconName)
                        ? $iconsUrl . '/' . $iconName
                        : $iconsUrl . '/txt.png',
                    'width' => 64,
                    'height' => 64,
                ];
            }
        }
        return $images;
    }

    public function getExtendedAttributes($preview = null)
    {
        if (!empty($preview)) {
            $this->previews = (array)$preview;
        }
        return $this->toArray();
    }

    /**
     * Checks if the file's image is of the given size or ratio
     *
     * @param array(integer, integer) $fixedSize
     * @return bool
     * @throws FileException
     * @throws YiiBaseException
     */
    public function checkImageFixedSize($fixedSize)
    {
        if (!$this->isImage()) {
            return true;
        }

        $originalImageMeta = $this->getImagePreview(FileModule::PREVIEW_ORIGINAL);
        $originalImageMeta->checkFixedSize((int)$fixedSize[0], (int)$fixedSize[1]);

        if ($originalImageMeta->hasErrors()) {
            return false;
        }

        return true;
    }

    public function isImage()
    {
        return FileImage::isImageMimeType($this->fileMimeType);
    }
}
