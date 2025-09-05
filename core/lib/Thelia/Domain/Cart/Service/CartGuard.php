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
use Thelia\Domain\Checkout\Exception\EmptyCartException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Checkout\Exception\MissingAddressException;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\ModuleQuery;

class CartGuard
{
    /**
     * @throws EmptyCartException|PropelException
     */
    public function checkCartNotEmpty(?Cart $cart): void
    {
        if (!$cart || $cart->countCartItems() === 0) {
            throw new EmptyCartException('Cart is empty or contains no items');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkDeliveryAddress(?Cart $cart): void
    {
        if (!$cart || !$cart->getAddressDeliveryId()) {
            throw new MissingAddressException('Delivery address is required');
        }

        $address = AddressQuery::create()->findPk($cart->getAddressDeliveryId());
        if (!$address) {
            throw new MissingAddressException('Delivery address not found');
        }
    }

    /**
     * @throws MissingAddressException
     */
    public function checkInvoiceAddress(?Cart $cart): void
    {
        if (!$cart || !$cart->getAddressInvoiceId()) {
            throw new MissingAddressException('Invoice address is required');
        }

        $address = AddressQuery::create()->findPk($cart->getAddressInvoiceId());
        if (!$address) {
            throw new MissingAddressException('Invoice address not found');
        }
    }

    /**
     * @throws InvalidDeliveryException
     */
    public function checkValidDelivery(?Cart $cart): void
    {
        $this->checkDeliveryAddress($cart);

        if (!$cart?->getDeliveryModuleId()) {
            throw new InvalidDeliveryException('Delivery module is required');
        }

        $module = ModuleQuery::create()->findPk($cart?->getDeliveryModuleId());
        if (!$module) {
            throw new InvalidDeliveryException('Delivery module not found');
        }
    }

    /**
     * @throws InvalidPaymentException
     */
    public function checkValidPayment(?Cart $cart): void
    {
        if (!$cart || !$cart->getPaymentModuleId()) {
            throw new InvalidPaymentException('Payment module is required');
        }

        $module = ModuleQuery::create()->findPk($cart->getPaymentModuleId());
        if (!$module) {
            throw new InvalidPaymentException('Payment module not found');
        }
    }
}
