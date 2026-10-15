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

namespace Thelia\Tests\Integration\Domain\Taxation\TaxEngine;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\CartAddress;
use Thelia\Model\Country;
use Thelia\Model\State;
use Thelia\Test\IntegrationTestCase;

final class TaxEngineTest extends IntegrationTestCase
{
    public function testOutsideAnyRequestTheGoodsAreTaxedForTheDefaultCountry(): void
    {
        $taxEngine = new TaxEngine(new RequestStack(), static::getContainer()->get('event_dispatcher'));

        self::assertSame(Country::getDefaultCountry()->getId(), $taxEngine->getDeliveryCountry()->getId());
        self::assertNull($taxEngine->getDeliveryState());
    }

    public function testACustomerWhoPickedNoDeliveryAddressIsTaxedForTheCountryOfItsDefaultAddress(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country(['isocode' => '994', 'isoalpha2' => 'XD', 'isoalpha3' => 'XDX']);
        $customerTitle = $factory->customerTitle();
        $customer = $factory->customer($customerTitle);
        $factory->address($customer, $country, $customerTitle)->setIsDefault(1)->save($this->getPropelConnection());

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->getCurrentRequest()->getSession()->setCustomerUser($customer);

        $taxEngine = new TaxEngine($requestStack, static::getContainer()->get('event_dispatcher'));

        self::assertNotSame(Country::getDefaultCountry()->getId(), $country->getId());
        self::assertSame($country->getId(), $taxEngine->getDeliveryCountry()->getId());
    }

    public function testTheStateOfTheDeliveryAddressIsKnownWithoutAskingForTheCountryFirst(): void
    {
        $factory = $this->createFixtureFactory();
        $country = $factory->country(['isocode' => '995', 'isoalpha2' => 'XE', 'isoalpha3' => 'XEX']);
        $state = (new State())
            ->setCountryId($country->getId())
            ->setIsocode('XE1')
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('Test state');
        $state->save($this->getPropelConnection());

        $customerTitle = $factory->customerTitle();
        $customer = $factory->customer($customerTitle);
        $deliveryAddress = (new CartAddress())
            ->setCustomerTitleId($customerTitle->getId())
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setAddress1('1 Main Street')
            ->setAddress2('')
            ->setAddress3('')
            ->setZipcode('1000')
            ->setCity('Springfield')
            ->setCountryId($country->getId())
            ->setStateId($state->getId());
        $deliveryAddress->save($this->getPropelConnection());

        $cart = $factory->cart($customer);
        $cart->setAddressDeliveryId($deliveryAddress->getId())->save($this->getPropelConnection());

        $requestStack = static::getContainer()->get('request_stack');
        $session = $requestStack->getCurrentRequest()->getSession();
        self::assertInstanceOf(Session::class, $session);
        // A cart that belongs to a customer is only handed back by the session to that customer.
        $session->setCustomerUser($customer);
        $session->setSessionCart($cart);

        $taxEngine = new TaxEngine($requestStack, static::getContainer()->get('event_dispatcher'));

        self::assertSame($state->getId(), $taxEngine->getDeliveryState()?->getId());
    }
}
