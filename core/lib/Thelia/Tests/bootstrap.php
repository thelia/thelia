<?php

/**
 *
 * @file
 * Functions needed for Thelia bootstrap
 */
$env = "test";
require_once __DIR__ . '/../../../bootstrap.php';



\Propel::init(THELIA_ROOT . "/core/lib/Thelia/Tests/Db/thelia-conf.php");
