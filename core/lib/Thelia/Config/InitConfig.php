<?php

namespace Thelia\Config;

use Composer\Script\Event;

class InitConfig 
{
    public static function initConfig(Event $event)
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');

        $packageConfigFiles = [
            'webpack_encore.yaml'
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
