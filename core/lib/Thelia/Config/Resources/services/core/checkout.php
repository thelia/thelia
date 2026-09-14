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

use Thelia\Domain\Checkout\Service\ConsentAcceptanceReaderInterface;
use Thelia\Domain\Checkout\Service\ConsentAcceptanceStore;

/*
 * Single substitution point for the consent answers the checkout reads: alias
 * ConsentAcceptanceReaderInterface to your own service from a module
 * configureServices() to have the progression, the payment step and the order
 * validation all read them from somewhere other than the session.
 *
 * The alias is public so that code holding only the container, such as a module
 * built on BaseModule, can fetch the reader without autowiring.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->alias(ConsentAcceptanceReaderInterface::class, ConsentAcceptanceStore::class)
        ->public();
};
