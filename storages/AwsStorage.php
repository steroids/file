<?php

namespace steroids\file\storages;

use frostealth\yii2\aws\s3\Service;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\helpers\FileHelper;
use function GuzzleHttp\Psr7\stream_for;
use function GuzzleHttp\Psr7\try_fopen;

class AwsStorage extends BaseStorage
{
    public string $key = '';

    public string $secret = '';

    /**
     * @var Service|null
     */
    private ?Service $amazoneStorage = null;

    /**
     * Configuration of amazon storage
     *
     * @var array
     */
    public array $config = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        /** @var Service $storage */
        $storage = Yii::createObject(array_merge(
            [
                'class' => Service::class,
                'region' => '',
                'credentials' => [
                    'key' => $this->key,
                    'secret' => $this->secret,
                ],
                'defaultBucket' => '',
                'defaultAcl' => 'public-read',
            ],
            $this->config
        ));

        $this->amazoneStorage = $storage;

        parent::init();
    }

    /**
     * @param File|FileImage $file
     * @return resource
     */
    public function read($file)
    {
        return try_fopen($file->getUrl(), 'r+');
    }

    /**
     * @param UploaderFile $uploaderFile
     * @param string $folderToSave Relative folder path to save
     * @return StorageResult
     */
    public function write(UploaderFile $uploaderFile, $folderToSave = '')
    {
        $fileFolder = trim($folderToSave, '/');
        $fileName = ($fileFolder ? $fileFolder . '/' : '') . $uploaderFile->name;
        $sourceResource = try_fopen($uploaderFile->source, 'r+');
        $sourceStream = stream_for($sourceResource);
        $uploaderFile->mimeType = (string)FileHelper::getMimeTypeByExtension($fileName);

        ob_start();
        $this->amazoneStorage
            ->commands()
            ->upload($fileName, $sourceStream)
            ->withContentType($uploaderFile->mimeType)
            ->execute();

        $url = $this->amazoneStorage->getUrl($fileName);
        ob_end_clean();
        $sourceStream->close();

        return new StorageResult([
            'url' => $url,
        ]);
    }

    /**
     * @param File|FileImage $file
     * @return string
     */
    public function resolvePath($file)
    {
        return null;
    }

    /**
     * @param FileImage $fileImage
     * @throws Throwable
     */
    public function deleteImageMeta($fileImage)
    {
        $imageMetaPath = $this->getFullPath($fileImage);
        // Delete image meta file
        $this->amazoneStorage->delete($imageMetaPath);
    }

    /**
     * @param File $file
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function delete($file)
    {
        $path = $this->getFullPath($file);

        $imagesMeta = FileImage::findAll(['fileId' => $file->id]);
        ob_start();
        foreach ($imagesMeta as $imageMeta) {
            $imageMeta->delete();
        }

        // Delete original file
        $this->amazoneStorage->delete($path);
        ob_end_clean();
    }

    /**
     * @param File|FileImage $file
     * @return string
     */
    public function resolveUrl($file)
    {
        return $file->amazoneS3Url;
    }
}
