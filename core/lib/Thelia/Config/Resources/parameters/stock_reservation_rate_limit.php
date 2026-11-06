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

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // How many times the stock of a cart may be held in an hour (the steps of the checkout,
        // and the changes of the cart in a shop that reserves from the cart on). A real buyer
        // holds a handful of times; the cart budget stops a script that repeats the hold on one
        // cart. The address budget stops a caller who spreads over many carts: it is the one to
        // raise for a shop whose buyers share an address.
        ->set('env(THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CART)', '60')
        ->set('env(THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CLIENT)', '300');
};
