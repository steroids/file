<?php

namespace steroids\file\structure;

use yii\base\Model;

/**
 * Class StorageResult
 */
class StorageResult extends Model
{
    public string $path = '';
    public string $url = '';
    public string $md5 = '';
}
