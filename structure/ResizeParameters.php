<?php

namespace steroids\file\structure;

use yii\base\BaseObject;

class ResizeParameters extends BaseObject
{
    public int $width = 0;
    public int $height = 0;
    public int $offsetX = 0;
    public int $offsetY = 0;
    public int $originalWidth = 0;
    public int $originalHeight = 0;
    public int $previewQuality = 90;
}