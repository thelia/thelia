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

namespace Thelia\Tests\Integration\Domain\Shipping;

use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Shipping\Service\DeliverySetupService;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Address;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\Customer;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * `cart.address_delivery_id` is a foreign key on `cart_address.id`. Writing an
 * `address.id` there is rejected outright by the database once the two
 * auto-increment sequences have drifted apart, and accepted silently — pointing
 * at somebody else's address — as long as they have not.
 *
 * Reading it back as an `address.id` has the same two outcomes, and the silent
 * one is the dangerous one: the country it yields drives the VAT rate.
 */
final class CartDeliveryAddressIdTest extends ActionIntegrationTestCase
{
    public function testSettingUpVirtualDeliveryStoresACartAddressId(): void
    {
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $country = $this->factory->country();
        $address = $this->factory->address($customer, $country, $customerTitle);
        $address->setIsDefault(1)->save($this->getPropelConnection());

        $cart = $this->factory->cart($customer);

        $this->getService(DeliverySetupService::class)->setupVirtualDelivery($cart);

        $storedId = $cart->getAddressDeliveryId();
        self::assertNotNull($storedId);

        $cartAddress = CartAddressQuery::create()->findPk($storedId);
        self::assertNotNull(
            $cartAddress,
            'The stored id must exist in cart_address, the table its foreign key points at.',
        );
        self::assertSame($address->getId(), $cartAddress->getAddressId());
        self::assertSame($country->getId(), $cartAddress->getCountryId());
    }

    public function testTheDeliveryCountryComesFromTheAddressTheCustomerPicked(): void
    {
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $deliveryCountry = $this->factory->country();
        $address = $this->factory->address($customer, $deliveryCountry, $customerTitle);

        $cart = $this->factory->cart($customer);
        $cart
            ->setAddressDeliveryId($this->cartAddressFor($address)->getId())
            ->save($this->getPropelConnection());

        // A cart that belongs to a customer is only handed back by the session
        // to that customer; anybody else gets a fresh one.
        $this->session()->setCustomerUser($customer);
        $this->session()->setSessionCart($cart);

        self::assertSame(
            $deliveryCountry->getId(),
            $this->getService(TaxEngine::class)->getDeliveryCountry()->getId(),
            'VAT must be computed on the country of the address the customer picked.',
        );
    }

    public function testTheCheckoutEventCarriesACustomerAddressId(): void
    {
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $address = $this->factory->address($customer, $this->factory->country(), $customerTitle);
        $cartAddress = $this->cartAddressFor($address);

        $cart = $this->factory->cart($customer);
        $cart
            ->setAddressDeliveryId($cartAddress->getId())
            ->setAddressInvoiceId($cartAddress->getId())
            ->save($this->getPropelConnection());

        $event = new CartCheckoutEvent($cart);

        // The listeners of that event look the id up in `address`, so the event
        // has to carry the customer address, not the cart's frozen copy of it.
        self::assertSame($address->getId(), $event->getDeliveryAddressId());
        self::assertSame($address->getId(), $event->getInvoiceAddressId());
    }

    public function testTheOrderExposesTheCustomerAddressItWasDeliveredTo(): void
    {
        $customerTitle = $this->factory->customerTitle();
        $customer = $this->factory->customer($customerTitle);
        $address = $this->factory->address($customer, $this->factory->country(), $customerTitle);
        $cartAddress = $this->cartAddressFor($address);

        $cart = $this->cartWithAddress($customer, $cartAddress);
        $order = $this->factory->order($customer);
        $order->setCartId($cart->getId())->save($this->getPropelConnection());

        self::assertSame($address->getId(), $order->getChoosenDeliveryAddress());
        self::assertSame($address->getId(), $order->getChoosenInvoiceAddress());
    }

    private function cartWithAddress(Customer $customer, CartAddress $cartAddress): \Thelia\Model\Cart
    {
        $cart = $this->factory->cart($customer);
        $cart
            ->setAddressDeliveryId($cartAddress->getId())
            ->setAddressInvoiceId($cartAddress->getId())
            ->save($this->getPropelConnection());

        return $cart;
    }

    private function cartAddressFor(Address $address): CartAddress
    {
        return $this->getService(CartAddressService::class)
            ->getOrCreateCartAddressFromAddress($address);
    }

    private function session(): Session
    {
        return static::getContainer()->get('request_stack')->getCurrentRequest()->getSession();
    }
}
