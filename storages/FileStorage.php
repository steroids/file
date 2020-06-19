<?php

namespace steroids\file\uploaders;

use steroids\file\exceptions\FileException;
use steroids\file\models\File;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use yii\helpers\FileHelper;

class FileStorage extends BaseStorage
{
    /**
     * Absolute path to root user files dir
     * @var string
     */
    public string $rootPath;

    /**
     * Absolute url to root user files dir
     * @var string
     */
    public string $rootUrl;

    public function init()
    {
        parent::init();

        // Default dirs
        $this->rootPath = FileHelper::normalizePath($this->rootPath ?: \Yii::getAlias('@webroot/assets'));
        $this->rootUrl = rtrim($this->rootUrl ?: \Yii::getAlias('@web', false) . '/assets', '/');
    }

    /**
     * @inheritDoc
     */
    public function write(UploaderFile $file, $folder = null)
    {
        $relativePath = implode(DIRECTORY_SEPARATOR, array_filter([
            $folder,
            $file->savedFileName
        ]));
        $path = $this->rootPath . DIRECTORY_SEPARATOR . $relativePath;

        // Create destination directory, if no exists
        if (!file_exists($path)) {
            FileHelper::createDirectory($path);
        }
        if (!is_writable($path)) {
            throw new FileException('Destination directory is not writable: ' . $path);
        }

        // Support content range
        if ($file->contentRange) {
            // Check file exists and correct size
            if ($file->contentRange->start > 0 && !is_file($path)) {
                throw new FileException('Not found file for append content: ' . $path);
            }

            $backendFileSize = filesize($path);

            // Check file size on server
            if ($file->contentRange->start > $backendFileSize) {
                throw new FileException('Incorrect content range size for append content. Start: '
                    . $file->contentRange->start . ', backend: ' . $backendFileSize
                );
            }

            // Truncate file, if it more than content-range start
            if ($file->contentRange->start < $backendFileSize) {
                $handle = fopen($path, 'r+');
                ftruncate($handle, $file->contentRange['start']);
                rewind($handle);
                fclose($handle);
            }
        }

        // Upload file content
        file_put_contents(
            $path,
            fopen('php://input', 'r'),
            $file->contentRange && $file->contentRange['start'] > 0 ? FILE_APPEND : 0
        );

        // Check real mime type from file
        $mimeType = FileHelper::getMimeType($path);
        $file->mimeType = is_string($mimeType) ? $mimeType : $file->mimeType;

        return [
            'md5' => is_readable($path) ? md5_file($path) : null,
        ];
    }

    /**
     * @param File $file
     * @return string
     */
    public function resolvePath(File $file)
    {
        return implode(DIRECTORY_SEPARATOR, array_filter([
            $this->rootPath,
            $file->folder,
            $file->fileName
        ]));
    }

    /**
     * @param File $file
     * @return string
     */
    public function resolveUrl(File $file)
    {
        return implode(DIRECTORY_SEPARATOR, array_filter([
            $this->rootUrl,
            $file->folder,
            $file->fileName
        ]));
    }
}
