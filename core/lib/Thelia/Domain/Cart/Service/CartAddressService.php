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
            ->setCustomerTitleId($address->getTitleId())
            ->setAddressId($address->getId())
            ->setCompany($address->getCompany())
            ->setFirstname($address->getFirstname())
            ->setLastname($address->getLastname())
            ->setAddress1($address->getAddress1())
            ->setAddress2($address->getAddress2())
            ->setAddress3($address->getAddress3())
            ->setZipcode($address->getZipcode())
            ->setCity($address->getCity())
            ->setPhone($address->getPhone())
            ->setCellphone($address->getCellphone())
            ->setCountryId($address->getCountryId())
            ->setStateId($address->getStateId())
            ->save();

        return $cartAddress;
    }
}
