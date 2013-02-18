<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
$env = "test";
require_once __DIR__ . '/../../../bootstrap.php';

\Propel::init(THELIA_ROOT . "/local/config/config_thelia.php");
