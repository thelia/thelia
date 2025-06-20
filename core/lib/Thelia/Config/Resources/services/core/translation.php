<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Translation\Translator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->alias(TranslatorInterface::class, Translator::class);

    $services->alias('thelia.translator', Translator::class);
};
