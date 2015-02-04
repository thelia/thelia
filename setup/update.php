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

$bootstrapToggle = false;
$bootstraped = false;

// Autoload bootstrap

foreach ($argv as $arg) {
    if ($arg === '-b') {
        $bootstrapToggle = true;

        continue;
    }

    if ($bootstrapToggle) {
        require __DIR__ . DIRECTORY_SEPARATOR . $arg;

        $bootstraped = true;
    }
}

if (!$bootstraped) {
    if (isset($bootstrapFile)) {
        require $bootstrapFile;
    } elseif (is_file($file = __DIR__ . '/../core/vendor/autoload.php')) {
        require $file;
    } elseif (is_file($file = __DIR__ . '/../../bootstrap.php')) {
        // Here we are on a thelia/thelia-project
       require $file;
    } else {
        echo "No autoload file found. Please use the -b argument to include yours";
        exit(1);
    }
}

if (php_sapi_name() != 'cli') {
    echo 'this script can only be launched with cli sapi' . PHP_EOL;
    exit(1);
}

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Exception\UpdateException;

/***************************************************
 * Load Update class
 ***************************************************/

try {
    $update = new \Thelia\Install\Update(false);
} catch (UpdateException $ex) {
    echo $ex->getMessage() . PHP_EOL;
    exit(2);
}

/***************************************************
 * Check if update is needed
 ***************************************************/

if ($update->isLatestVersion()) {
    echo "You already have the latest version of Thelia : " . $update->getCurrentVersion() . PHP_EOL;
    exit(3);
}

while (1) {
    echo sprintf(
        "You are going to update Thelia from version %s to version %s." . PHP_EOL,
        $update->getCurrentVersion(),
        $update->getLatestVersion()
    );
    echo "Continue update process ? (Y/n)" . PHP_EOL;

    $rep = readStdin(true);
    if ($rep == 'y') {
        break;
    } elseif ($rep == 'n') {
        echo "Update aborted" . PHP_EOL;
        exit(0);
    }
}

$backup = false;
while (1) {
    echo sprintf("Would you like to backup the current database before proceeding ? (Y/n)" . PHP_EOL);

    $rep = readStdin(true);
    if ($rep == 'y') {
        $backup = true;
        break;
    } elseif ($rep == 'n') {
        $backup = false;
        break;
    }
}

/***************************************************
 * Update
 ***************************************************/

$updateError = null;

try {
    // backup db
    if (true === $backup) {
        try {
            $update->backupDb();
            echo sprintf(PHP_EOL . 'Your database has been backed up. The sql file : %s'. PHP_EOL, $update->getBackupFile());
        } catch (\Exception $e) {
            echo PHP_EOL . 'Sorry, your database can\'t be backed up. Reason : ' . $e->getMessage() . PHP_EOL;
            exit(4);
        }
    }
    // update
    $update->process($backup);
} catch (UpdateException $ex) {
    $updateError = $ex;
}

if (null === $updateError) {
    echo sprintf(PHP_EOL . 'Thelia as been successfully updated to version %s' . PHP_EOL, $update->getCurrentVersion());
} else {
    echo sprintf(PHP_EOL . 'Sorry, an unexpected error has occured : %s' . PHP_EOL, $updateError->getMessage());
    print $updateError->getTraceAsString() . PHP_EOL;
    print "Trace: " . PHP_EOL;
    foreach ($update->getLogs() as $log) {
        echo sprintf('[%s] %s' . PHP_EOL, $log[0], $log[1]);
    }

    if (true === $backup) {

        while (1) {
            echo "Would you like to restore the backup database ? (Y/n)" . PHP_EOL;

            $rep = readStdin(true);
            if ($rep == 'y') {

                echo "Database restore started. Wait, it could take a while..." . PHP_EOL;

                if (false === $update->restoreDb()) {
                    echo sprintf(
                        PHP_EOL . 'Sorry, your database can\'t be restore. Try to do it manually : %s' . PHP_EOL,
                        $update->getBackupFile()
                    );
                    exit(5);
                } else {
                    echo "Database successfully restore." . PHP_EOL;
                    exit(5);
                }
                break;
            } elseif ($rep == 'n') {
                exit(0);
            }
        }

    }
}

/***************************************************
 * Try to delete cache
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
