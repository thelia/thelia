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
use Thelia\Model\CartAddress;
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

        $deliveryOrderAddress = $this->freeze($deliveryAddress, $connection);
        $invoiceOrderAddress = $this->freeze($invoiceAddress, $connection);

        $order->setDeliveryOrderAddressId($deliveryOrderAddress->getId());
        $order->setInvoiceOrderAddressId($invoiceOrderAddress->getId());

        return $deliveryAddress->getCountry();
    }

    /**
     * Copies a cart address, field by field, into an order address of its own: the order
     * keeps what the buyer had entered even when the cart address is edited or deleted.
     */
    private function freeze(CartAddress $cartAddress, ConnectionInterface $connection): OrderAddress
    {
        $orderAddress = (new OrderAddress())
            ->setCustomerTitleId($cartAddress->getCustomerTitleId())
            ->setCompany($cartAddress->getCompany())
            ->setSiret($cartAddress->getSiret())
            ->setVatNumber($cartAddress->getVatNumber())
            ->setFirstname($cartAddress->getFirstname())
            ->setLastname($cartAddress->getLastname())
            ->setAddress1($cartAddress->getAddress1())
            ->setAddress2($cartAddress->getAddress2())
            ->setAddress3($cartAddress->getAddress3())
            ->setZipcode($cartAddress->getZipcode())
            ->setCity($cartAddress->getCity())
            ->setPhone($cartAddress->getPhone())
            ->setCellphone($cartAddress->getCellphone())
            ->setCountryId($cartAddress->getCountryId())
            ->setStateId($cartAddress->getStateId());
        $orderAddress->save($connection);

        return $orderAddress;
    }
}
