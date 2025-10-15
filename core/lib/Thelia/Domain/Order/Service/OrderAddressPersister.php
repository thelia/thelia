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

namespace Thelia\Domain\Order\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Country;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;

readonly class OrderAddressPersister
{
    public function prepareOrderAddresses(
        ModelOrder $order,
        CartModel $cart,
        bool $useOrderDefinedAddresses,
        ConnectionInterface $connection,
    ): Country {
        if ($useOrderDefinedAddresses) {
            return OrderAddressQuery::create()
                ->findPk($order->getDeliveryOrderAddressId())
                ->getCountry();
        }

        $deliveryAddress = CartAddressQuery::create()->findPk($cart->getAddressDeliveryId());
        $invoiceAddress = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());

        $deliveryOrderAddress = (new OrderAddress())
            ->setCustomerTitleId($deliveryAddress->getCustomerTitleId())
            ->setCompany($deliveryAddress->getCompany())
            ->setFirstname($deliveryAddress->getFirstname())
            ->setLastname($deliveryAddress->getLastname())
            ->setAddress1($deliveryAddress->getAddress1())
            ->setAddress2($deliveryAddress->getAddress2())
            ->setAddress3($deliveryAddress->getAddress3())
            ->setZipcode($deliveryAddress->getZipcode())
            ->setCity($deliveryAddress->getCity())
            ->setPhone($deliveryAddress->getPhone())
            ->setCellphone($deliveryAddress->getCellphone())
            ->setCountryId($deliveryAddress->getCountryId())
            ->setStateId($deliveryAddress->getStateId());
        $deliveryOrderAddress->save($connection);

        $invoiceOrderAddress = (new OrderAddress())
            ->setCustomerTitleId($invoiceAddress->getCustomerTitleId())
            ->setCompany($invoiceAddress->getCompany())
            ->setFirstname($invoiceAddress->getFirstname())
            ->setLastname($invoiceAddress->getLastname())
            ->setAddress1($invoiceAddress->getAddress1())
            ->setAddress2($invoiceAddress->getAddress2())
            ->setAddress3($invoiceAddress->getAddress3())
            ->setZipcode($invoiceAddress->getZipcode())
            ->setCity($invoiceAddress->getCity())
            ->setPhone($invoiceAddress->getPhone())
            ->setCellphone($invoiceAddress->getCellphone())
            ->setCountryId($invoiceAddress->getCountryId())
            ->setStateId($deliveryAddress->getStateId());
        $invoiceOrderAddress->save($connection);

        $order->setDeliveryOrderAddressId($deliveryOrderAddress->getId());
        $order->setInvoiceOrderAddressId($invoiceOrderAddress->getId());

        return $deliveryAddress->getCountry();
    }
}
