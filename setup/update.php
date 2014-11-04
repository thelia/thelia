<?php

/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

define('DS', DIRECTORY_SEPARATOR);
define('THELIA_ROOT', rtrim(realpath(dirname(__DIR__)), DS) . DS);
define('THELIA_LOCAL_DIR', THELIA_ROOT . 'local' . DS);
define('THELIA_CONF_DIR', THELIA_LOCAL_DIR . 'config' . DS);
define('THELIA_MODULE_DIR', THELIA_LOCAL_DIR . 'modules' . DS);
define('THELIA_WEB_DIR', THELIA_ROOT . 'web' . DS);
define('THELIA_CACHE_DIR', THELIA_ROOT . 'cache' . DS);
define('THELIA_LOG_DIR', THELIA_ROOT . 'log' . DS);
define('THELIA_TEMPLATE_DIR', THELIA_ROOT . 'templates' . DS);

$loader = require __DIR__ . "/../core/vendor/autoload.php";

if (php_sapi_name() != 'cli') {
    echo 'this script can only be launched with cli sapi' . PHP_EOL;
    exit(1);
}

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/***************************************************
 * retrieve the root dir
 ***************************************************/

$rootPath = realpath(dirname(dirname(__FILE__)));

/***************************************************
 * Get database config
 ***************************************************/

// Load local/config/database.yml
$databasePath = joinPaths($rootPath, 'local', 'config', 'database.yml');

if (!(file_exists($databasePath) && is_readable($databasePath))) {
    echo "Thelia is not installed (no database.yml file)" . PHP_EOL;
    exit(2);
}

try {
    $dbConfig = Yaml::parse($databasePath);
    $dbConfig = $dbConfig['database']['connection'];
} catch (ParseException $ex) {
    echo "database.yml is not a valid file : " . $ex->getMessage() . PHP_EOL;
    exit(3);
}

/***************************************************
 * Get a connection to DB
 ***************************************************/

$dbConn = null;
try {
    $dbConn = new PDO(
        $dbConfig['dsn'],
        $dbConfig['user'],
        $dbConfig['password']
    );
} catch (PDOException $e) {
    echo "Error connecting to db : " . $e->getMessage() . PHP_EOL;
    exit(4);
}

/***************************************************
 * Get Versions
 ***************************************************/

$update = new \Thelia\Install\Update();
$versions = $update->getVersion();
$latestVersion = end($versions);
$currentVersion = null;

try {
    $stmt = $dbConn->prepare('SELECT * from config where name = ? LIMIT 1');
    $stmt->execute(['thelia_version']);
    if (false !== $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentVersion = $row['value'];
    }
} catch (PDOException $e) {
    echo "Error retrieving current version : " . $e->getMessage() . PHP_EOL;
    exit(4);
}

if (null === $currentVersion) {
    echo "Error retrieving current version" . PHP_EOL;
    exit(5);
}

/***************************************************
 * Update process
 ***************************************************/

if (version_compare($latestVersion, $currentVersion, "<=")) {
    echo "You already have the latest version of Thelia : " . $currentVersion . PHP_EOL;
    exit(6);
}

echo sprintf("Would you like to upgrade from version %s to version %s ?" . PHP_EOL, $currentVersion, $latestVersion);
echo "Please make a full backup of your site before (database, files)" . PHP_EOL;

while (1) {
    echo sprintf("Continue update process ? (Y/n)" . PHP_EOL, $currentVersion, $latestVersion);

    $rep = readStdin(true);
    if ($rep == 'y') {
        break;
    } elseif ($rep == 'n') {
        exit(0);
    }
}

$dbConn->beginTransaction();
$updatePath = joinPaths($rootPath, 'setup', 'update');
$database = new \Thelia\Install\Database($dbConn);

try {
    foreach ($versions as $number => $version) {
        if (version_compare($version, $currentVersion, ">")) {
            echo sprintf(PHP_EOL . "== Update to version %s ==" . PHP_EOL, $version);

            // sql update
            $sqlPath = joinPaths($updatePath, $version . '.sql');
            if (is_readable($sqlPath)) {
                echo sprintf("Importing sql : %s" . PHP_EOL, $sqlPath);
                $database->insertSql(null, [$sqlPath]);
            }

            // php update
            $phpPath = joinPaths($updatePath, $version . '.php');
            if (is_readable($phpPath)) {
                echo sprintf("Executing php : %s" . PHP_EOL, $phpPath);
                include_once $phpPath;
            }

            // todo: provide a mechanism to update module
            // it could be : for activated modules, look in module directory in update/thelia/$version.(sql|php)
        }
    }
} catch (\Exception $ex) {
    echo sprintf("Error in update process : %s" . PHP_EOL, $ex->getMessage());
    echo "All databases update will be rolled back" . PHP_EOL;
    $dbConn->rollBack();
    exit(7);
}

$dbConn->commit();

$dbConn = null;


/***************************************************
Try to delete cache
***************************************************/

$finder = new Finder();
$fs = new Filesystem();
$hasDeleteError = false;

$finder->files()->in(THELIA_CACHE_DIR);

echo sprintf("Try to delete cache in : %s" . PHP_EOL, THELIA_CACHE_DIR);

foreach ($finder as $file) {
    try {
        $fs->remove($file);
    } catch (\Symfony\Component\Filesystem\Exception\IOException $ex) {
        $hasDeleteError = true;
    }
}

if (true === $hasDeleteError) {
    echo "The cache has not been cleared properly. Try to run the command manually : " .
        "(sudo) php Thelia cache:clear (--env=prod)." . PHP_EOL;
}

echo "Update process finished." . PHP_EOL;

exit(0);


/***************************************************
 * Utils
 ***************************************************/

function readStdin($normalize = false)
{
    $fr = fopen("php://stdin", "r");
    $input = fgets($fr, 128);
    $input = rtrim($input);
    fclose($fr);

    if ($normalize) {
        $input = strtolower(trim($input));
    }

    return $input;
}

function joinPaths()
{
    $args = func_get_args();
    $paths = [];

    foreach ($args as $arg) {
        $paths[] = trim($arg, '/\\');
    }

    $path = join(DIRECTORY_SEPARATOR, $paths);
    if (substr($args[0], 0, 1) === '/') {
        $path = DIRECTORY_SEPARATOR . $path;
    }

    return $path;
}
