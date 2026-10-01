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

namespace Thelia\Model;

use Thelia\Model\Base\CartAddress as BaseCartAddress;

class CartAddress extends BaseCartAddress
{
    public function copyFrom(Address $address): static
    {
        return $this
            ->setCustomerTitleId($address->getTitleId())
            ->setAddressId($address->getId())
            ->setCompany($address->getCompany())
            ->setSiret($address->getSiret())
            ->setVatNumber($address->getVatNumber())
            ->setVatVerifiedAt($address->getVatVerifiedAt())
            ->setVatVerifiedName($address->getVatVerifiedName())
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
            ->setStateId($address->getStateId());
    }
}
