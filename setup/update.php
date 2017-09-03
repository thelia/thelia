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
        cliOutput('No autoload file found. Please use the -b argument to include yours', 'error');
        exit(1);
    }
}

if (php_sapi_name() != 'cli') {
    cliOutput('this script can only be launched with cli sapi', 'error');
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
    cliOutput($ex->getMessage(), 'error');
    exit(2);
}

/***************************************************
 * Check if update is needed
 ***************************************************/

if ($update->isLatestVersion()) {
    cliOutput("You already have the latest version of Thelia : " . $update->getCurrentVersion(), 'success');
    exit(3);
}

$current = $update->getCurrentVersion();
$files   = $update->getLatestVersion();
$web     = $update->getWebVersion();

while (1) {
    if ($web !== null && $files != $web) {
        cliOutput(sprintf(
            "Thelia server is reporting the current stable release version is %s ",
            $web
        ), 'warning');
    }

    cliOutput(sprintf(
        "You are going to update Thelia from version %s to version %s.",
        $current,
        $files
    ), 'info');

    if ($web !== null && $files < $web) {
        cliOutput(sprintf(
            "Your files belongs to version %s, which is not the latest stable release.",
            $files
        ), 'warning');
        cliOutput(sprintf(
            "It is recommended to upgrade your files first then run this script again." . PHP_EOL
            . "The latest version is available at http://thelia.net/#download ."
        ), 'warning');
        cliOutput("Continue update process anyway ? (Y/n)");
    } else {
        cliOutput("Continue update process ? (Y/n)");
    }

    $rep = readStdin(true);
    if ($rep == 'y') {
        break;
    } elseif ($rep == 'n') {
        cliOutput("Update aborted", 'warning');
        exit(0);
    }
}

$backup = false;
while (1) {
    cliOutput(sprintf("Would you like to backup the current database before proceeding ? (Y/n)"));

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
            cliOutput(sprintf('Your database has been backed up. The sql file : %s', $update->getBackupFile()), 'info');
        } catch (\Exception $e) {
            cliOutput('Sorry, your database can\'t be backed up. Reason : ' . $e->getMessage(), 'error');
            exit(4);
        }
    }
    // update
    $update->process($backup);
} catch (UpdateException $ex) {
    $updateError = $ex;
}



foreach ($update->getMessages() as $message) {
    cliOutput($message[0], $message[1]);
}

if (null === $updateError) {
    cliOutput(sprintf('Thelia as been successfully updated to version %s', $update->getCurrentVersion()), 'success');
    if ($update->hasPostInstructions()) {
        cliOutput('===================================');
        cliOutput($update->getPostInstructions());
        cliOutput('===================================');
    }

} else {
    cliOutput(sprintf('Sorry, an unexpected error has occured : %s', $updateError->getMessage()), 'error');
    print $updateError->getTraceAsString() . PHP_EOL;
    print "Trace: " . PHP_EOL;
    foreach ($update->getLogs() as $log) {
        cliOutput(sprintf('[%s] %s' . PHP_EOL, $log[0], $log[1]), 'error');
    }

    if (true === $backup) {
        while (1) {
            cliOutput("Would you like to restore the backup database ? (Y/n)");

            $rep = readStdin(true);
            if ($rep == 'y') {
                cliOutput("Database restore started. Wait, it could take a while...");

                if (false === $update->restoreDb()) {
                    cliOutput(sprintf(
                        'Sorry, your database can\'t be restore. Try to do it manually : %s',
                        $update->getBackupFile()
                    ), 'error');
                    exit(5);
                } else {
                    cliOutput("Database successfully restore.");
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

cliOutput(sprintf("Try to delete cache in : %s", THELIA_CACHE_DIR), 'info');

foreach ($finder as $file) {
    try {
        $fs->remove($file);
    } catch (\Symfony\Component\Filesystem\Exception\IOException $ex) {
        $hasDeleteError = true;
    }
}

if (true === $hasDeleteError) {
    cliOutput("The cache has not been cleared properly. Try to run the command manually : " .
        "(sudo) php Thelia cache:clear (--env=prod).");
}

cliOutput("Update process finished.", 'info');
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

function cliOutput($message, $type = null)
{
    switch ($type) {
        case 'success':
            $color = "\033[0;32m";
            break;
        case 'info':
            $color = "\033[0;34m";
            break;
        case 'error':
            $color = "\033[0;31m";
            break;
        case 'warning':
            $color = "\033[1;33m";
            break;
        default:
            $color = "\033[0m";
    }

    echo PHP_EOL . $color . $message . "\033[0m" . PHP_EOL;
}
