<?php

namespace steroids\file\tests\unit;

use PHPUnit\Framework\TestCase;
use steroids\file\FileModule;
use steroids\file\processors\ImageCropResize;
use steroids\file\structure\UploaderFile;
use steroids\file\storages\FileStorage;
use yii\base\Exception;

class FileTest extends TestCase
{
    /**
     * Upload text file
     *
     * @throws Exception
     */
    public function testFile()
    {
        $module = FileModule::getInstance();

        $fileName = 'text.txt';
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

        $file = $module->uploadFromFile($uploadedFile, null, FileModule::STORAGE_FILE);

        $this->assertTrue(file_exists($rootFilePath . DIRECTORY_SEPARATOR . $file->fileName));
    }

//    /**
//     * @throws Exception
//     * @throws Throwable
//     */
//    public function testDeleteFile()
//    {
//        $module = FileModule::getInstance();
//        $fileName = 'test-img.jpg';
//        $rootFilePath = dirname(__DIR__) . '/testData';
//
//        $module->previews = [
//            FileModule::PREVIEW_ORIGINAL => [
//                'class' => ImageCropResize::class,
//                'width' => 1920,
//                'height' => 1200,
//            ],
//            FileModule::PREVIEW_DEFAULT => [
//                'class' => ImageCropResize::class,
//                'width' => 800,
//                'height' => 600,
//            ],
//        ];
//
//        $module->storages = [
//            'file' => [
//                'class' => FileStorage::class,
//                'rootPath' => $rootFilePath,
//                'rootUrl' => $rootFilePath,
//            ]
//        ];
//
//        $uploadedFile = new UploaderFile([
//            'name' => $fileName,
//            'source' => $rootFilePath . DIRECTORY_SEPARATOR . $fileName,
//        ]);
//
//        $file = $module->uploadFromFile($uploadedFile, null, FileModule::STORAGE_FILE);
//
//        $file->delete();
//
//        $deletedFile = FileImage::findOriginal($file->id);
//
//        $this->assertFalse(file_exists($rootFilePath . DIRECTORY_SEPARATOR . $file->fileName));
//
//        $this->assertNull($deletedFile);
//    }
}
