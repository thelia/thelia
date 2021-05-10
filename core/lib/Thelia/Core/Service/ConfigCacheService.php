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

namespace Thelia\Core\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\VarExporter\VarExporter;
use Thelia\Model\ConfigQuery;

class ConfigCacheService
{
    /**
     * @var string
     */
    private $kernelCacheDir;

    public function __construct(
        string $kernelCacheDir
    ) {
        $this->kernelCacheDir = $kernelCacheDir;
    }

    public function initCacheConfigs(bool $force = false): void
    {
        if ($force || !file_exists($this->kernelCacheDir.DS.'thelia_configs.php')) {
            $caches = [];

            $configs = ConfigQuery::create()->find();

            foreach ($configs as $config) {
                $caches[$config->getName()] = $config->getValue();
            }

            (new Filesystem())->dumpFile(
                $this->kernelCacheDir.DS.'thelia_configs.php',
                '<?php return '.VarExporter::export($caches).';'
            );
        }

        ConfigQuery::initCache(
            require $this->kernelCacheDir.DS.'thelia_configs.php'
        );
    }
}
