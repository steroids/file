<?php

namespace steroids\file\controllers;

use steroids\file\exceptions\FileException;
use steroids\file\FileModule;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\processors\ImageCrop;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class CropController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return array
     * @throws FileException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $file = File::findOrPanic(['uid' => Yii::$app->request->post('uid')]);

        $coordinates = Yii::$app->request->post('coordinates');
        foreach ($coordinates as $previewName => $percents) {
            $image = $file->getImagePreview($previewName);
            list($width, $height) = getimagesizefromstring(file_get_contents(FileImage::findOriginal($image->fileId)->getPath()));

            $image->preview([
                'class' => ImageCrop::class,
                'offsetX' => $width * ($percents['x'] / 100),
                'offsetY' => $height * ($percents['y'] / 100),
                'width' => $width * ($percents['width'] / 100),
                'height' => $height * ($percents['height'] / 100),
            ]);
        }

        return $file->getExtendedAttributes(array_merge([FileModule::PREVIEW_DEFAULT], array_keys($coordinates)));
    }
}
