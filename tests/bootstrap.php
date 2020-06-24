<?php

use steroids\core\base\ConsoleApplication;

define('STEROIDS_ROOT_DIR', realpath(__DIR__ . '/../../..'));
define('YII_ENV', 'test');

$config = require __DIR__ . '/../../../bootstrap.php';

new ConsoleApplication($config);
