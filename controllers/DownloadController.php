<?php

namespace steroids\file\controllers;

use steroids\file\FileModule;
use steroids\file\models\File;
use Yii;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class DownloadController extends Controller
{
    /**
     * @param string $uid
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionIndex($uid)
    {
        /** @var File $file */
        $file = File::findOne(['uid' => $uid]);
        if (!$file) {
            throw new NotFoundHttpException();
        }

        if (FileModule::getInstance()->xHeader !== false) {
            Yii::$app->response->xSendFile($file->path, $file->downloadName, [
                'xHeader' => FileModule::getInstance()->xHeader,
            ]);
        } else {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file->getDownloadName() . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file->path));
            readfile($file->path);
        }
    }
}