<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$loader = require 'vendor/autoload.php';

$loader->addPsr4('', THELIA_ROOT.'var/cache/test/propel/model');
$loader->addPsr4('TheliaMain\\', THELIA_ROOT.'var/cache/test/propel/database/TheliaMain');
$loader->register();
