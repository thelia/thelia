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

use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Translation\Translator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(TranslatorInterface::class, Translator::class)->public();
    $services->alias('thelia.translator', Translator::class)->public();

    // Services
    $services->set('translation.loader.php', '%translation.loader.php.class%')
        ->tag('translation.loader', ['alias' => 'php']);

    $services->set('translation.loader.yml', '%translation.loader.yml.class%')
        ->tag('translation.loader', ['alias' => 'yml', 'legacy-alias' => 'yaml']);

    $services->set('translation.loader.xliff', '%translation.loader.xliff.class%')
        ->tag('translation.loader', ['alias' => 'xlf', 'legacy-alias' => 'xliff']);

    $services->set('translation.loader.po', '%translation.loader.po.class%')
        ->tag('translation.loader', ['alias' => 'po']);

    $services->set('translation.loader.mo', '%translation.loader.mo.class%')
        ->tag('translation.loader', ['alias' => 'mo']);

    $services->set('translation.loader.qt', '%translation.loader.qt.class%')
        ->tag('translation.loader', ['alias' => 'ts']);

    $services->set('translation.loader.csv', '%translation.loader.csv.class%')
        ->tag('translation.loader', ['alias' => 'csv']);

    $services->set('translation.loader.res', '%translation.loader.res.class%')
        ->tag('translation.loader', ['alias' => 'res']);

    $services->set('translation.loader.dat', '%translation.loader.dat.class%')
        ->tag('translation.loader', ['alias' => 'dat']);

    $services->set('translation.loader.ini', '%translation.loader.ini.class%')
        ->tag('translation.loader', ['alias' => 'ini']);
};
