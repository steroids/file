<?php

namespace steroids\file\migrations;

use steroids\core\base\Migration;

class M000000000003File extends Migration
{
    public function safeUp()
    {
        if (!$this->db->getTableSchema('files')) {
            $this->createTable('files', [
                'id' => $this->primaryKey(),
                'uid' => $this->string(36),
                'title' => $this->string(),
                'folder' => $this->string(),
                'fileName' => $this->string(),
                'fileMimeType' => $this->string(),
                'fileSize' => $this->integer(),
                'createTime' => $this->dateTime(),
                'updateTime' => $this->dateTime(),
                'isTemp' => $this->boolean(),
                'storageName' => $this->string('32'),
                'amazoneS3Url' => $this->text(),
                'md5' => $this->string(),
                'userId' => $this->integer(),
            ]);
            $this->createIndex('uid', 'files', 'uid');
        }

        if (!$this->db->getTableSchema('file_images')) {
            $this->createTable('file_images', [
                'id' => $this->primaryKey(),
                'fileId' => $this->integer(),
                'folder' => $this->string(),
                'fileName' => $this->string(),
                'fileMimeType' => $this->string(),
                'isOriginal' => $this->boolean(),
                'width' => $this->smallInteger(),
                'height' => $this->smallInteger(),
                'previewName' => $this->string(),
                'createTime' => $this->dateTime(),
                'updateTime' => $this->dateTime(),
                'amazoneS3Url' => $this->text(),
            ]);
            $this->createIndex('file_preview', 'file_images', [
                'fileId',
                'previewName',
            ]);
            $this->createIndex('original', 'file_images', [
                'fileId',
                'isOriginal',
            ]);
        }
    }

    public function safeDown()
    {
        $this->dropTable('files');
        $this->dropTable('file_images');
    }
}
