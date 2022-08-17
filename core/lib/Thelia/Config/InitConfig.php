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

use Composer\Installer\PackageEvent;

class InitConfig
{
    public static function initConfig(PackageEvent $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $packageConfigFiles = [
            'webpack_encore.yaml',
        ];

        $initConfigPackagesDir = __DIR__.'/Resources/packages';

        $configDir = $vendorDir.'/../config';

        $configPackagesDir = $configDir.'/packages';

        if (is_dir($configDir) === false) {
            mkdir($configDir);
        }

        if (is_dir($configPackagesDir) === false) {
            mkdir($configPackagesDir);
        }

        foreach ($packageConfigFiles as $packageConfigFile) {
            $destinationFile = $configPackagesDir.'/'.$packageConfigFile;
            if (file_exists($destinationFile)) {
                continue;
            }
            copy($initConfigPackagesDir.'/'.$packageConfigFile, $destinationFile);
        }
    }
}
