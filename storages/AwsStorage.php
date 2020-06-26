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
use yii\helpers\Url;
use function GuzzleHttp\Psr7\stream_for;
use function GuzzleHttp\Psr7\try_fopen;

class AwsStorage extends Storage
{
    public string $key = '';

    public string $secret = '';

    /**
     * @var Service|object|null
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
     * @param UploaderFile $file
     * @return resource
     */
    public function read(UploaderFile $file)
    {
        if (is_resource($file->source)) {
            return $file->source;
        }
        return try_fopen($file->source,'r');
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
        return $file->amazoneS3Url;
    }

    /**
     * @param File|FileImage $file
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function delete($file)
    {
        $path = $this->getFullFileName($file);

        $imagesMeta = FileImage::findAll(['fileId' => $file->id]);
        ob_start();
        foreach ($imagesMeta as $imageMeta) {
            $imageMetaPath = $this->getFullFileName($imageMeta);
            // Delete image meta file
            $this->amazoneStorage->delete($imageMetaPath);
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

    /**
     * @param File|FileImage $file
     * @return string
     */
    public function resolveDownloadUrl($file)
    {
        return Url::to(['/file/download/index', 'uid' => $file->uid, 'name' => $file->getDownloadName()], true);
    }
}
