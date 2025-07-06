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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\IcuDatFileLoader;
use Symfony\Component\Translation\Loader\IcuResFileLoader;
use Symfony\Component\Translation\Loader\IniFileLoader;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\QtFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;

return static function (ContainerConfigurator $container): void {
    $parameters = $container->parameters();

    $parameters->set('translation.loader.php.class', PhpFileLoader::class);
    $parameters->set('translation.loader.yml.class', YamlFileLoader::class);
    $parameters->set('translation.loader.xliff.class', XliffFileLoader::class);
    $parameters->set('translation.loader.po.class', PoFileLoader::class);
    $parameters->set('translation.loader.mo.class', MoFileLoader::class);
    $parameters->set('translation.loader.qt.class', QtFileLoader::class);
    $parameters->set('translation.loader.csv.class', CsvFileLoader::class);
    $parameters->set('translation.loader.res.class', IcuResFileLoader::class);
    $parameters->set('translation.loader.dat.class', IcuDatFileLoader::class);
    $parameters->set('translation.loader.ini.class', IniFileLoader::class);
};
