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

namespace Thelia\Tests\Integration\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Test\ActionIntegrationTestCase;

final class AddressCartCopyRefreshTest extends ActionIntegrationTestCase
{
    private const VERIFIED_NUMBER = 'BE0123456789';

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testMovingTheChosenBillingAddressToTheShopCountryStopsTheExemption(): void
    {
        [$title, $address, $cart] = $this->cartBilledToAVerifiedBelgianAddress();

        $this->updateAddress($address, $title, $this->country('FR'), company: 'Acme', vatNumber: self::VERIFIED_NUMBER);

        $copy = $this->invoiceCopyOf($cart);
        self::assertSame($this->country('FR')->getId(), $copy->getCountryId());
        self::assertNull($copy->getVatVerifiedAt());
        $cart->reload(true);
        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testEmptyingTheCompanyOfTheChosenBillingAddressStopsTheExemption(): void
    {
        [$title, $address, $cart] = $this->cartBilledToAVerifiedBelgianAddress();

        $this->updateAddress($address, $title, $this->country('BE'), company: '', vatNumber: self::VERIFIED_NUMBER);

        $copy = $this->invoiceCopyOf($cart);
        self::assertNull($copy->getVatNumber());
        self::assertNull($copy->getVatVerifiedAt());
        $cart->reload(true);
        self::assertFalse($this->resolver()->isExemptedForCart($cart));
    }

    public function testCorrectingTheZipcodeKeepsTheVerificationOfTheCopy(): void
    {
        [$title, $address, $cart] = $this->cartBilledToAVerifiedBelgianAddress();

        $this->updateAddress($address, $title, $this->country('BE'), company: 'Acme', vatNumber: self::VERIFIED_NUMBER);

        $copy = $this->invoiceCopyOf($cart);
        self::assertSame('75001', $copy->getZipcode());
        self::assertNotNull($copy->getVatVerifiedAt());
        $cart->reload(true);
        self::assertTrue($this->resolver()->isExemptedForCart($cart));
    }

    public function testTheDeliveryCopyKeepsTheCountryItsPostageWasQuotedFor(): void
    {
        [$title, $address, $cart] = $this->cartBilledToAVerifiedBelgianAddress();
        $deliveryCopy = (new CartAddressService())->getOrCreateCartAddressFromAddress($address);
        $cart->setAddressDeliveryId($deliveryCopy->getId())->save($this->getPropelConnection());

        $this->updateAddress($address, $title, $this->country('FR'), company: 'Acme', vatNumber: self::VERIFIED_NUMBER);

        CartAddressTableMap::clearInstancePool();
        self::assertSame($this->country('BE')->getId(), CartAddressQuery::create()->findPk($deliveryCopy->getId())?->getCountryId());
    }

    public function testTheCopyOfACartAlreadyOrderedIsLeftAsOrdered(): void
    {
        [$title, $address, $cart] = $this->cartBilledToAVerifiedBelgianAddress();
        $order = $this->factory->order();
        $order->setCartId($cart->getId())->save($this->getPropelConnection());

        $this->updateAddress($address, $title, $this->country('FR'), company: 'Acme', vatNumber: self::VERIFIED_NUMBER);

        self::assertSame($this->country('BE')->getId(), $this->invoiceCopyOf($cart)->getCountryId());
    }

    /**
     * @return array{0: CustomerTitle, 1: Address, 2: Cart}
     */
    private function cartBilledToAVerifiedBelgianAddress(): array
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('vat_verification_lifetime_days', '90');
        ConfigQuery::write('store_country', (string) $this->country('FR')->getId());

        $title = $this->factory->customerTitle();
        $customer = $this->factory->customer($title);
        $address = $this->factory->address($customer, $this->country('BE'), $title, ['zipcode' => '1000', 'city' => 'Bruxelles']);
        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?')
            ->execute(['Acme', self::VERIFIED_NUMBER, (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme SPRL', $address->getId()]);
        $address = $this->reloaded($address);

        $cart = $this->factory->cart($customer);
        $copy = (new CartAddressService())->getOrCreateCartAddressFromAddress($address);
        $cart->setAddressInvoiceId($copy->getId())->save($this->getPropelConnection());

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        $cart->reload(true);
        self::assertTrue($this->resolver()->isExemptedForCart($cart), 'Control: the cart starts exempt.');

        return [$title, $address, $cart];
    }

    private function updateAddress(Address $address, CustomerTitle $title, Country $country, ?string $company, ?string $vatNumber): void
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);

        $event = new AddressCreateOrUpdateEvent(
            'Billing address',
            $title->getId(),
            'John',
            'Doe',
            (string) $address->getAddress1(),
            '',
            '',
            '75001',
            (string) $address->getCity(),
            $country->getId(),
            '',
            '',
            $company,
            false,
            null,
            null,
            $vatNumber,
        );
        $event->setAddress($address);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->dispatch($event, TheliaEvents::ADDRESS_UPDATE);

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
    }

    private function invoiceCopyOf(Cart $cart): \Thelia\Model\CartAddress
    {
        CartAddressTableMap::clearInstancePool();
        $copy = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());
        self::assertNotNull($copy);

        return $copy;
    }

    private function country(string $isoAlpha2): Country
    {
        $country = CountryQuery::create()->findOneByIsoalpha2($isoAlpha2);
        self::assertNotNull($country);

        return $country;
    }

    private function reloaded(Address $address): Address
    {
        $reloaded = AddressQuery::create()->findPk($address->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    private function resolver(): VatExemptionResolver
    {
        return $this->getService(VatExemptionResolver::class);
    }
}
