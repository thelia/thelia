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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Thelia\Domain\Cart\Exception\InvalidCartException;

/**
 * A product refused at the addition to the cart because the known destination of the
 * cart cannot receive it. Still an InvalidCartException for whoever adds a product;
 * a promotion that offers this product tells it apart to leave its gift out.
 */
final class ProductRefusedByDestinationException extends InvalidCartException
{
}
