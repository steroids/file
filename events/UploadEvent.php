<?php

namespace steroids\file\events;

use steroids\file\models\File;
use steroids\file\structure\UploadOptions;
use yii\base\Event;

class UploadEvent extends Event
{
    public bool $isValid = true;

    public UploadOptions $options;
}