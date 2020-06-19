<?php

namespace steroids\file\structure;

use yii\base\BaseObject;

class UploaderContentRange extends BaseObject
{
    public int $start;
    public int $end;
    public int $total;
}
