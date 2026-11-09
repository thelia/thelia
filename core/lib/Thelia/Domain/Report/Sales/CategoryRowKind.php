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

namespace Thelia\Domain\Report\Sales;

/**
 * What a row of the sales by category stands for: a category of the catalogue, the goods
 * no category holds (a product deleted since, or filed nowhere), or the services the shop
 * invoiced beside the goods (a gift wrapping).
 */
enum CategoryRowKind: string
{
    case Category = 'category';
    case Uncategorized = 'uncategorized';
    case Services = 'services';
}
