<?php

namespace steroids\file\storages;

use frostealth\yii2\aws\s3\Service;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use Yii;
use yii\base\InvalidConfigException;
use function GuzzleHttp\Psr7\stream_for;
use function GuzzleHttp\Psr7\try_fopen;

class AwsStorage extends BaseStorage
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
     * @return string
     */
    public function resolveRelativePath($file)
    {
        return $file->amazoneS3Url;
    }

    /**
     * @param File $file
     * @return string
     */
    public function delete($file)
    {
        $this->amazoneStorage->delete($file->fileName);
        return true;
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
        return $file->amazoneS3Url;
    }
}
