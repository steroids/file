<?php

namespace steroids\file\schemas;

use steroids\file\FileModule;
use steroids\core\base\BaseSchema;
use steroids\file\exceptions\FileException;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class ImageSchema extends BaseSchema
{
    /**
     * @param int|null $fileId
     * @return static|File|null
     */
    public static function createFromFileId($fileId)
    {
        $file = $fileId ? File::findOne(['id' => $fileId]) : null;
        return  $file ? new static(['model' => $file]) : null;
    }

    /**
     * @var File
     */
    public $model;

    /**
     * @var FileImage[]
     */
    protected array $_images = [];

    public function fields()
    {
        return [
            'uid',
            'fileName',
            'thumbnailUrl',
            'thumbnailWidth',
            'thumbnailHeight',
            'fullUrl',
            'fullWidth',
            'fullHeight',
        ];
    }

    /**
     * @return string|null
     * @throws FileException
     * @throws Exception
     */
    public function getThumbnailUrl()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_THUMBNAIL), 'url');
    }

    /**
     * @return int|null
     * @throws FileException
     * @throws Exception
     */
    public function getThumbnailWidth()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_THUMBNAIL), 'width');
    }

    /**
     * @return int|null
     * @throws FileException
     * @throws Exception
     */
    public function getThumbnailHeight()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_THUMBNAIL), 'height');
    }

    /**
     * @return string|null
     * @throws FileException
     * @throws Exception
     */
    public function getFullUrl()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_FULLSCREEN), 'url');
    }

    /**
     * @return int|null
     * @throws FileException
     * @throws Exception
     */
    public function getFullWidth()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_FULLSCREEN), 'width');
    }

    /**
     * @return int|null
     * @throws FileException
     * @throws Exception
     */
    public function getFullHeight()
    {
        return ArrayHelper::getValue($this->getImage(FileModule::PREVIEW_FULLSCREEN), 'height');
    }

    /**
     * @param string $previewName
     * @return FileImage|null
     * @throws \steroids\file\exceptions\FileException
     * @throws \yii\base\Exception
     */
    protected function getImage(string $previewName)
    {
        if (!isset($this->_images[$previewName])) {
            $this->_images[$previewName] = $this->model->isImage()
                ? $this->model->getImagePreview($previewName)
                : null;
        }
        return $this->_images[$previewName];
    }

}