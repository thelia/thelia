<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Autoload\ClassLoader;
use Symfony\Component\Dotenv\Dotenv;

/** @var ClassLoader $loader */
$loader = require dirname(__DIR__).'/vendor/autoload.php';

$propelCacheDir = dirname(__DIR__).'/var/cache/test/propel/model';
if (is_dir($propelCacheDir)) {
    $loader->addPsr4('', $propelCacheDir);
    $loader->addPsr4('TheliaMain\\', dirname(__DIR__).'/var/cache/test/propel/database/TheliaMain');
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
