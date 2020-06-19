<?php

namespace steroids\file\uploaders;

use steroids\file\models\File;

class AwsStorage extends BaseStorage
{
    public string $key;
    public string $secret;

    /**
     * @param File $file File model
     * @param mixed $source Source or path
     * @return mixed
     */
    public function write(File $file, $source)
    {

        $this->amazoneStorage = \Yii::createObject(array_merge(
            [
                'class' => 'frostealth\yii2\aws\s3\Service',
                'region' => '',
                'credentials' => [
                    'key' => '',
                    'secret' => '',
                ],
                'defaultBucket' => '',
                'defaultAcl' => 'public-read',
            ],
            $this->amazoneStorage ?: []
        ));

        $folder = trim($file->folder, '/');
        $fileName = ($folder ? $folder . '/' : '') . $file->fileName;
        $sourceResource = Psr7\try_fopen($sourcePath ?: $file->path, 'r+');
        $sourceStream = Psr7\stream_for($sourceResource);
        ob_start();
        $this->amazoneStorage
            ->commands()
            ->upload($fileName, $sourceStream)
            ->withContentType($file->fileMimeType)
            ->execute();
        $url = $this->amazoneStorage->getUrl($fileName);
        ob_end_clean();
        $sourceStream->close();

        return [
            'amazoneS3Url' => $url,
        ];
    }

    /**
     * @param File $file
     * @return string
     */
    public function resolvePath(File $file)
    {
        return null;
    }

    /**
     * @param File $file
     * @return string
     */
    public function resolveUrl(File $file)
    {
        return $file->amazoneS3Url;
    }
}
