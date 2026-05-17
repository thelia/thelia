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

// Propel generates the Base and Map model classes that Thelia\Model\* extends.
// They live under var/propel/<env>/model/ and are normally produced at kernel
// boot — except the boot path bails out via TheliaKernel::isInstalled() when
// no database is reachable (Scrutinizer, cold CI). Without the Base classes
// PHPStan reports class.noParent on every model and hits the 1000-error cap.
//
// Drive the Propel generator directly here so PHPStan always has the full
// class graph, regardless of database availability. We bypass the full kernel
// boot (which would crash on autoload-time references to the still-missing
// Base classes) and call the build steps that don't need a connection.
$baseModelDir = THELIA_ROOT.'var'.DS.'propel'.DS.'test'.DS.'model'.DS.'Thelia'.DS.'Model'.DS.'Base';
if (!is_dir($baseModelDir)) {
    $schemaLocator = new Thelia\Core\Propel\Schema\SchemaLocator(
        THELIA_CONF_DIR,
        THELIA_MODULE_DIR,
        THELIA_LOCAL_MODULE_DIR,
    );

    $propelInit = new Thelia\Core\Propel\PropelInitService(
        environment: 'test',
        debug: false,
        envParameters: [
            'thelia.database_host' => '',
            'thelia.database_port' => '3306',
            'thelia.database_name' => '',
            'thelia.database_user' => '',
            'thelia.database_password' => '',
        ],
        schemaLocator: $schemaLocator,
    );

    $propelInit->buildPropelConfig();
    $propelInit->buildPropelInitFile();
    $propelInit->buildPropelGlobalSchema();
    $propelInit->buildPropelModels();
}

$loader->addPsr4('', THELIA_ROOT.'var/propel/test/model');
$loader->addPsr4('TheliaMain\\', THELIA_ROOT.'var/propel/test/database/TheliaMain');
$loader->register();
