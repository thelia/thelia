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
use Thelia\Test\TestToken;

/** @var ClassLoader $loader */
$loader = require dirname(__DIR__).'/vendor/autoload.php';

// A process running next to others (TEST_TOKEN) has its own models once its
// database is prepared; the unit suite never prepares one and reads the shared
// models, which phpstan_autoload.php builds without a database.
foreach (array_unique(['test'.TestToken::suffix(), 'test']) as $propelDirectory) {
    $propelCacheDir = dirname(__DIR__).'/var/propel/'.$propelDirectory.'/model';
    if (is_dir($propelCacheDir)) {
        $loader->addPsr4('', $propelCacheDir);
        $loader->addPsr4('TheliaMain\\', dirname(__DIR__).'/var/propel/'.$propelDirectory.'/database/TheliaMain');
        break;
    }
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// In test mode, Symfony Dotenv skips .env.local by design (test isolation).
// However, the database connection is typically the same in dev and test.
// Bridge DATABASE_* vars from .env.local when they are not already defined
// (by CI env, phpunit.xml, .env.test, or .env.test.local).
if (empty($_SERVER['DATABASE_HOST'])) {
    $envLocalFile = dirname(__DIR__).'/.env.local';
    if (file_exists($envLocalFile)) {
        $localVars = (new Dotenv())->parse(file_get_contents($envLocalFile));
        $dbKeys = ['DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_PASSWORD'];
        foreach ($dbKeys as $key) {
            if (isset($localVars[$key]) && empty($_SERVER[$key])) {
                $_SERVER[$key] = $_ENV[$key] = $localVars[$key];
            }
        }
    }
}

// Each process next to others gets its own database and lock directory: the
// suites lock on row ids, which are the same in every copy of the database.
if (null !== TestToken::current()) {
    TestToken::useOwnDatabase();

    $lockDirectory = dirname(__DIR__).'/var/lock/test'.TestToken::suffix();
    if (!is_dir($lockDirectory)) {
        mkdir($lockDirectory, 0o777, true);
    }
    $_SERVER['LOCK_DSN'] = $_ENV['LOCK_DSN'] = 'flock://'.$lockDirectory;
    putenv('LOCK_DSN=flock://'.$lockDirectory);
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
