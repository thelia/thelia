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

namespace Thelia\Domain\Cart\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Address;
use Thelia\Model\CartAddress;

class CartAddressService
{
    /**
     * @throws PropelException
     */
    public function getOrCreateCartAddressFromAddress(
        Address $address,
        ?CartAddress $cartAddress = null,
    ): CartAddress {
        $cartAddress ??= new CartAddress();

        $cartAddress
            ->copyFrom($address)
            ->save();

        return $cartAddress;
    }
}
