<?php

namespace steroids\file\uploaders;

use steroids\file\structure\UploaderContentRange;
use steroids\file\structure\UploaderFile;
use yii\helpers\ArrayHelper;

class PutUploader extends BaseUploader
{
    public function upload()
    {
        // Parse the Content-Disposition header
        $fileName = null;
        if (!empty($_SERVER['HTTP_CONTENT_DISPOSITION'])) {
            $fileName = rawurldecode(preg_replace('/(^[^"]+")|("$)/', '', $_SERVER['HTTP_CONTENT_DISPOSITION']));
        }
        if (!$fileName) {
            $fileName = \Yii::$app->request->get('name') ?: \Yii::$app->security->generateRandomString(10);
        }

        // Parse the Content-Range header, which has the following form:
        // Content-Range: bytes 0-524287/2000000
        $contentRange = null;
        if (!empty($_SERVER['HTTP_CONTENT_RANGE']) && preg_match('/([0-9]+)-([0-9]+)\/([0-9]+)/', $_SERVER['HTTP_CONTENT_RANGE'], $match)) {
            $contentRange = new UploaderContentRange([
                'start' => (int)$match[1],
                'end' => (int)$match[2],
                'total' => (int)$match[3],
            ]);
        }

        // Get file size
        $fileSize = null;
        if ($contentRange) {
            $fileSize = $contentRange->total;
        } elseif (!empty($_SERVER['CONTENT_LENGTH'])) {
            $fileSize = $_SERVER['CONTENT_LENGTH'];
        }

        // Get file type
        $fileMimeType = null;
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            $fileMimeType = $_SERVER['CONTENT_TYPE'];
        }

        return [
            new UploaderFile([
                'uid' => ArrayHelper::getValue($_GET, 'uids.0') ?: ArrayHelper::getValue($_GET, 'uid'),
                'name' => $fileName,
                'size' => $fileSize,
                'mimeType' => $fileMimeType,
                'contentRange' => $contentRange,
                'source' => fopen('php://input', 'r'),
            ])
        ];
    }
}
