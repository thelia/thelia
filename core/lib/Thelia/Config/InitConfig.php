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

namespace Thelia\Config;

use Composer\Script\Event;

class InitConfig
{
    public static function initConfig(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $packageConfigFiles = [
            'webpack_encore.yaml',
        ];

        $ConfigReplacementDir = __DIR__.'/ConfigReplacement';

        $configDir = $vendorDir.'/../config';

        $configPackagesDir = $configDir.'/packages';

        if (is_dir($configDir) === false) {
            mkdir($configDir);
        }

        if (is_dir($configPackagesDir) === false) {
            mkdir($configPackagesDir);
        }

        foreach ($packageConfigFiles as $packageConfigFile) {
            $baseFile = $ConfigReplacementDir.'/base_'.$packageConfigFile;
            $theliaFile = $ConfigReplacementDir.'/thelia_'.$packageConfigFile;
            $destinationFile = $configPackagesDir.'/'.$packageConfigFile;
            if (file_exists($destinationFile) && sha1_file($baseFile) !== sha1_file($destinationFile)) {
                continue;
            }
            copy($theliaFile, $destinationFile);
        }
    }
}
