<?php

namespace steroids\file\events;

use steroids\file\models\File;
use steroids\file\structure\UploadOptions;
use yii\base\Event;

class UploadAfterEvent extends Event
{
    public UploadOptions $options;

    public ?File $file;
}