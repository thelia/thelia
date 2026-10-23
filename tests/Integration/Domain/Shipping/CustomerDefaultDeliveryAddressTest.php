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

use Thelia\Domain\Shipping\ShippingFacade;
use Thelia\Model\CartAddressQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The default address of the customer, put on the cart as its delivery address, as a pickup
 * of the Flexy theme does for a customer with an account.
 */
final class CustomerDefaultDeliveryAddressTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    /**
     * The default address of a guest row may be another buyer's: nothing of it reaches the
     * cart, which keeps the address it has.
     */
    public function testAGuestCartIsLeftAsItIs(): void
    {
        $guest = $this->factory->guestCustomer($this->factory->customerTitle());
        $this->factory->address($guest, $this->factory->country(), null, ['address1' => '9 Another Buyer Street'])
            ->setIsDefault(1)
            ->save($this->getPropelConnection());
        $ownCopy = $this->factory->cartAddress(null, $this->factory->country(), null, ['address1' => '1 Own Street']);
        $cart = $this->factory->cart($guest);
        $cart->setAddressDeliveryId($ownCopy->getId())->save($this->getPropelConnection());
        $emptyCart = $this->factory->cart($guest);

        self::assertFalse($this->shipping()->setCustomerDefaultDeliveryAddress($cart));
        self::assertFalse($this->shipping()->setCustomerDefaultDeliveryAddress($emptyCart));

        self::assertSame($ownCopy->getId(), $cart->getAddressDeliveryId());
        self::assertSame('1 Own Street', $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1());
        self::assertNull($emptyCart->getAddressDeliveryId());
    }

    /**
     * The default address is written into the copy the cart has: no second copy, no copy
     * left behind.
     */
    public function testTheCopyOfACustomerCartIsRewrittenInPlace(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $default = $this->factory->address($customer, $this->factory->country(), null, ['address1' => '3 Default Street']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        // A copy typed by hand, with no source address, elsewhere: replaced all the same.
        $copy = $this->factory->cartAddress(null, $this->factory->country(), null, ['address1' => '4 Former Street', 'city' => 'Lyon']);
        $cart = $this->factory->cart($customer);
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());
        $copiesBefore = CartAddressQuery::create()->count($this->getPropelConnection());

        self::assertTrue($this->shipping()->setCustomerDefaultDeliveryAddress($cart), 'Another place: the carrier is priced again.');

        self::assertSame($copy->getId(), $cart->getAddressDeliveryId());
        self::assertSame('3 Default Street', $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1());
        self::assertSame($default->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());
        self::assertSame($copiesBefore, CartAddressQuery::create()->count($this->getPropelConnection()));
    }

    public function testACustomerCartWithoutACopyGetsOne(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $default = $this->factory->address($customer, $this->factory->country(), null, ['address1' => '5 Default Street']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        $cart = $this->factory->cart($customer);

        self::assertTrue($this->shipping()->setCustomerDefaultDeliveryAddress($cart), 'A cart without a copy is delivered somewhere new.');

        self::assertSame('5 Default Street', $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1());
        self::assertSame($default->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId());
    }

    /**
     * The cart carries a copy of the default address, which moved to another city since: the
     * copy keeps its id and its source, and only the answer tells the caller the place changed.
     */
    public function testACopyOfTheDefaultAddressMovedSinceIsAnotherPlace(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $country = $this->factory->country();
        $default = $this->factory->address($customer, $country, null, ['address1' => '8 Default Street', 'city' => 'Paris']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        $copy = $this->factory->cartAddress($default, $country);
        $cart = $this->factory->cart($customer);
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());
        $default->setCity('Lyon')->save($this->getPropelConnection());

        self::assertTrue($this->shipping()->setCustomerDefaultDeliveryAddress($cart));
        self::assertSame($copy->getId(), $cart->getAddressDeliveryId(), 'The same copy.');
        self::assertSame($default->getId(), $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddressId(), 'From the same source.');
        self::assertSame('Lyon', $cart->getCartAddressRelatedByAddressDeliveryId()?->getCity());
    }

    /**
     * Another street in the same place: the carriers read the same country, state, postal
     * code and city, nothing to price again.
     */
    public function testAnotherStreetOfTheSamePlaceIsNoNewPlace(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $country = $this->factory->country();
        $default = $this->factory->address($customer, $country, null, ['address1' => '6 Default Street', 'zipcode' => '75001', 'city' => 'Paris']);
        $default->setIsDefault(1)->save($this->getPropelConnection());
        $copy = $this->factory->cartAddress(null, $country, null, ['address1' => '7 Other Street', 'zipcode' => '75001', 'city' => 'Paris']);
        $cart = $this->factory->cart($customer);
        $cart->setAddressDeliveryId($copy->getId())->save($this->getPropelConnection());

        self::assertFalse($this->shipping()->setCustomerDefaultDeliveryAddress($cart));
        self::assertSame('6 Default Street', $cart->getCartAddressRelatedByAddressDeliveryId()?->getAddress1());
    }

    private function shipping(): ShippingFacade
    {
        return $this->getService(ShippingFacade::class);
    }
}
