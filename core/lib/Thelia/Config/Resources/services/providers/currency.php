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

use Thelia\CurrencyConverter\CurrencyConverter;
use Thelia\CurrencyConverter\Provider\ECBProvider;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // Currency converter
    $services->set('currency.converter', CurrencyConverter::class);

    $services->alias(CurrencyConverter::class, 'currency.converter');

    $services->set('currency.converter.ecbProvider', ECBProvider::class)
        ->tag('currency.converter.provider', ['priority' => 0]);
};
