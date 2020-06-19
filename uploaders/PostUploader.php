<?php

namespace steroids\file\uploaders;

use steroids\file\exceptions\FileUserException;
use steroids\file\structure\UploaderFile;
use yii\helpers\ArrayHelper;

class PostUploader extends BaseUploader
{
    public function upload()
    {
        $files = [];
        foreach ($this->normalizePostFiles(reset($_FILES)) as $index => $postFile) {
            // Check PHP upload errors
            switch ($postFile['error']) {
                case UPLOAD_ERR_NO_FILE:
                    throw new FileUserException(\Yii::t('steroids', 'Not found file.'));

                case UPLOAD_ERR_PARTIAL:
                    throw new FileUserException(\Yii::t('steroids', 'The file was corrupted when downloading. Please try again.'));

                case UPLOAD_ERR_FORM_SIZE:
                case UPLOAD_ERR_INI_SIZE:
                    throw new FileUserException(\Yii::t('steroids', 'The downloaded file is too large.'));

                case UPLOAD_ERR_OK:
                    break;

                default:
                    throw new FileUserException(\Yii::t('steroids', 'Error loading file. Error code `{code}`.', [
                        'code' => $postFile['error'],
                    ]));
            }

            $files[] = new UploaderFile([
                'uid' => ArrayHelper::getValue($_GET, ['uids', $index]),
                'name' => $postFile['name'],
                'size' => $postFile['size'],
                'mimeType' => $postFile['mimeType'],
                'source' => $postFile['tmp_name'],
                'rawData' => $postFile,
            ]);
        }
        return $files;
    }

    /**
     * @param array $postFile
     * @return array
     */
    protected function normalizePostFiles($postFile)
    {
        if (empty($postFile)) {
            return [];
        }
        if (!is_array($postFile['name'])) {
            return [$postFile];
        }

        $files = [];
        foreach ($postFile as $key => $values) {
            foreach ($values as $i => $value) {
                if (!isset($files[$i])) {
                    $files[$i] = [];
                }
                $files[$i][$key] = $value;
            }
        }
        return $files;
    }
}
