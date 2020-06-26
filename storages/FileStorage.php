<?php

namespace steroids\file\storages;

use steroids\file\exceptions\FileException;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\StorageResult;
use steroids\file\structure\UploaderFile;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\Url;

class FileStorage extends Storage
{
    /**
     * Absolute path to root user files dir
     * @var string
     */
    public string $rootPath = '';

    /**
     * Absolute url to root user files dir
     * @var string
     */
    public string $rootUrl = '';

    public function init()
    {
        parent::init();

        // Default dirs
        $this->rootPath = FileHelper::normalizePath($this->rootPath ?: Yii::getAlias('@webroot/assets'));
        $this->rootUrl = rtrim($this->rootUrl ?: Yii::getAlias('@web', false) . '/assets', '/');
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
        return fopen($file->source, 'r');
    }

    /**
     * @inheritDoc
     *
     * @throws FileException
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function write(UploaderFile $file, $folder = null)
    {
        $relativePath = implode(DIRECTORY_SEPARATOR, array_filter([
            $folder,
            $file->savedFileName
        ]));
        $folderPath = implode(DIRECTORY_SEPARATOR, array_filter([
            $this->rootPath,
            $folder
        ]));

        $path = $this->rootPath . DIRECTORY_SEPARATOR . $relativePath;

        // Create destination directory, if no exists
        if (!is_dir($folderPath)) {
            FileHelper::createDirectory($folderPath);
        }

        if (!is_writable($folderPath)) {
            throw new FileException('Destination directory is not writable: ' . $folderPath);
        }

        // Upload file content
        file_put_contents(
            $path,
            stream_get_contents($this->read($file)),
            $file->contentRange && $file->contentRange['start'] > 0 ? FILE_APPEND : 0
        );

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

        // Check real mime type from file
        $mimeType = FileHelper::getMimeType($path);
        $file->mimeType = is_string($mimeType) ? $mimeType : $file->mimeType;

        return new StorageResult([
            'md5' => is_readable($path) ? md5_file($path) : null,
        ]);
    }

    /**
     * @param File|FileImage $file
     * @return string
     */
    public function resolvePath($file)
    {
        return $this->getFullFileName($file, $this->rootPath);
    }

    /**
     * @param File|FileImage $file
     * @return string
     */
    public function resolveUrl($file)
    {
        return $this->getFullFileName($file, $this->rootUrl);
    }

    /**
     * @param File|FileImage $file
     * @param string|null $root
     * @return string
     */
    protected function getFullFileName($file, $root = null)
    {
        return implode(DIRECTORY_SEPARATOR, array_filter([
            $root,
            $file->folder !== DIRECTORY_SEPARATOR ? $file->folder : null,
            $file->fileName
        ]));
    }

    /**
     * @param File $file
     * @throws Exception
     * @throws FileException
     * @throws Throwable
     */
    public function delete($file)
    {
        // Remove image meta info
        $imagesMeta = FileImage::findAll(['fileId' => $file->id]);
        foreach ($imagesMeta as $imageMeta) {
            $imageMetaPath = $imageMeta->getPath();
            if (file_exists($imageMetaPath) && !unlink($imageMetaPath)) {
                throw new FileException('Can not remove image thumb file `' . $imageMetaPath . '`.');
            }
            $imageMeta->delete();
        }

        $filesRootPath = $this->rootPath;
        $filePath = $this->resolvePath($file);

        // Delete file
        if (file_exists($filePath) && !unlink($filePath)) {
            throw new FileException('Can not remove file file `' . $filePath . '`.');
        }

        // Check to delete empty folders
        $folderNames = explode('/', trim($file->folder, '/'));
        foreach ($folderNames as $i => $folderName) {
            $folderPath = implode('/', array_slice($folderNames, 0, count($folderNames) - $i)) . '/';
            $folderAbsolutePath = $filesRootPath . $folderPath;

            // Check dir exists
            if (!file_exists($folderAbsolutePath)) {
                continue;
            }

            // Skip, if dir is not empty
            $handle = opendir($folderAbsolutePath);
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    break 2;
                }
            }

            // Remove folder
            if (!rmdir($folderAbsolutePath)) {
                throw new FileException('Can not remove empty folder `' . $folderPath . '`.');
            }
        }
    }

    /**
     * @param File $file
     * @return string
     */
    public function resolveDownloadUrl($file)
    {
        return Url::to(['/file/download/index', 'uid' => $file->uid, 'name' => $file->getDownloadName()], true);
    }
}
