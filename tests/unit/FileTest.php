<?php

namespace steroids\file\tests\unit;

use PHPUnit\Framework\TestCase;
use steroids\file\exceptions\FileUserException;
use steroids\file\FileModule;
use steroids\file\models\FileImage;
use steroids\file\previews\ImageCropResize;
use steroids\file\structure\UploaderFile;
use steroids\file\storages\FileStorage;
use steroids\file\structure\UploadOptions;
use Throwable;
use yii\base\Exception;

class FileTest extends TestCase
{
    /**
     * Upload text file
     *
     * @throws Exception
     */
    public function testSaveFile()
    {
        $module = FileModule::getInstance();

        $fileName = 'text.txt';
        $rootFilePath = dirname(__DIR__) . '/testData';

        $module->storages = [
            'file' => [
                'class' => FileStorage::class,
                'rootPath' => $rootFilePath,
                'rootUrl' => $rootFilePath,
            ]
        ];

        $uploadedFile = new UploaderFile([
            'name' => $fileName,
            'source' => $rootFilePath . DIRECTORY_SEPARATOR . $fileName,
        ]);

        $file = $module->upload(new UploadOptions([
            'source' => $uploadedFile,
            'storageName' => FileModule::STORAGE_FILE,
        ]));

        $this->assertTrue(file_exists($rootFilePath . DIRECTORY_SEPARATOR . $file->fileName));
    }

    /**
     * Upload image
     *
     * @throws Exception
     */
    public function testSaveImage()
    {
        $module = FileModule::getInstance();

        $rootFilePath = dirname(__DIR__) . '/testData';

        $module->previews = [
            FileModule::PREVIEW_ORIGINAL => [
                'class' => ImageCropResize::class,
                'width' => 1920,
                'height' => 1200,
            ],
            FileModule::PREVIEW_DEFAULT => [
                'class' => ImageCropResize::class,
                'width' => 800,
                'height' => 600,
            ],
        ];

        $module->storages = [
            'file' => [
                'class' => FileStorage::class,
                'rootPath' => $rootFilePath,
                'rootUrl' => $rootFilePath,
            ]
        ];

        $this->expectException(FileUserException::class);
        $module->upload(new UploadOptions([
            'source' => $rootFilePath . DIRECTORY_SEPARATOR . 'text.txt',
            'storageName' => FileModule::STORAGE_FILE,
            'imagesOnly' => true,
        ]));

        $this->expectException(null);
        $file = $module->upload(new UploadOptions([
            'source' => $rootFilePath . DIRECTORY_SEPARATOR . 'beach.jpeg',
            'storageName' => FileModule::STORAGE_FILE,
            'imagesOnly' => true,
        ]));

        $this->assertTrue(file_exists($rootFilePath . DIRECTORY_SEPARATOR . $file->fileName));
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function testDeleteFile()
    {
        $module = FileModule::getInstance();
        $fileName = 'beach.jpeg';
        $rootFilePath = dirname(__DIR__) . '/testData';

        $module->previews = [
            FileModule::PREVIEW_ORIGINAL => [
                'class' => ImageCropResize::class,
                'width' => 1920,
                'height' => 1200,
            ],
            FileModule::PREVIEW_DEFAULT => [
                'class' => ImageCropResize::class,
                'width' => 800,
                'height' => 600,
            ],
        ];

        $module->storages = [
            'file' => [
                'class' => FileStorage::class,
                'rootPath' => $rootFilePath,
                'rootUrl' => $rootFilePath,
            ]
        ];

        $uploadedFile = new UploaderFile([
            'name' => $fileName,
            'source' => $rootFilePath . DIRECTORY_SEPARATOR . $fileName,
        ]);

        $file = $module->upload(new UploadOptions([
            'source' => $uploadedFile,
            'storageName' => FileModule::STORAGE_FILE,
        ]));

        $file->delete();

        $deletedFile = FileImage::findOriginal($file->id);

        $this->assertFalse(file_exists($rootFilePath . DIRECTORY_SEPARATOR . $file->fileName));

        $this->assertNull($deletedFile);
    }
}
