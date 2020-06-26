<?php

namespace steroids\file\controllers;

use steroids\file\exceptions\FileException;
use steroids\file\FileModule;
use steroids\file\models\File;
use steroids\file\models\FileImage;
use steroids\file\structure\UploaderFile;
use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\web\Controller;

class UploadController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return array|array[]
     * @throws Exception
     */
    public function actionIndex()
    {
        $mimeTypes = Yii::$app->request->get('mimeTypes');
        $fixedSize = Yii::$app->request->get('fixedSize');
        $source = Yii::$app->request->get('source');

        $uploaderFile = new UploaderFile();
        $uploaderFile->source = $source;
        $uploaderFile->size = is_string($fixedSize) ? explode(',', $fixedSize) : $fixedSize;
        $uploaderFile->mimeType = (string)(is_string($mimeTypes) ? explode(',', $mimeTypes) : $mimeTypes);

        $result = FileModule::getInstance()->uploadFromRequest(null, $uploaderFile);

        if (isset($result['errors'])) {
            return [
                'error' => implode(', ', $result['errors']),
            ];
        }

        $processor = array_filter(explode(',', (string)Yii::$app->request->get('imagesProcessor') ?: Yii::$app->request->get('processor')));

        // Send responses data
        return array_map(
            function ($file) use ($processor) {
                /** @var File $file */
                return $file->getExtendedAttributes($processor);
            },
            $result
        );
    }

    /**
     * @param null $CKEditorFuncNum
     * @return string
     * @throws FileException
     * @throws Exception
     */
    public function actionEditor($CKEditorFuncNum = null)
    {
        $result = FileModule::getInstance()->uploadFromRequest();
        if (!isset($result['errors'])) {
            /** @var File $file */
            $file = $result[0];
            $url = FileImage::findByPreviewName($file->id, FileModule::PREVIEW_ORIGINAL)->url;

            if ($CKEditorFuncNum) {
                return '<script>window.parent.CKEDITOR.tools.callFunction(' . Json::encode($CKEditorFuncNum) . ', ' . Json::encode($url) . ', "");</script>';
            } else {
                $result = [
                    'fileName' => $file->fileName,
                    'uploaded' => 1,
                    'url' => $url,
                ];
                if (Yii::$app->request->get('uids')) {
                    $result = [$result];
                }
                return Json::encode($result);
            }
        }
    }
}
