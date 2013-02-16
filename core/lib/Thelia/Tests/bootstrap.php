<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
$env = "test";
define('THELIA_ROOT', __DIR__ .'/../../../../');
$loader = require __DIR__ . '/../../../vendor/autoload.php';

require THELIA_ROOT . '/local/config/config_db.php';

\Propel::init(THELIA_ROOT . "/local/config/config_thelia.php");
