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
        // "untaxed_price_order" on the front product collection sorts on what the visitor
        // pays when a group, a discount, a rule or an operation may price for them, which
        // is resolved product by product. Past this many products in the narrowed listing
        // the sort ranks on the public prices instead, in SQL. An operating setting: what
        // a shop can price per request depends on its hosting, so it is read from the
        // environment.
        ->set('env(THELIA_API_PRODUCT_PRICE_SORT_MAX_PRODUCTS)', '5000')
        ->set('thelia.api.product_price_sort.max_products', '%env(int:THELIA_API_PRODUCT_PRICE_SORT_MAX_PRODUCTS)%');
};
