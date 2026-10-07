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

namespace Thelia\Tests\Api\Front;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Test\ApiTestCase;

final class CheckoutVatVerificationApiTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testChoosingAgainAnAddressWhoseVerificationWasRevokedStopsTheExemption(): void
    {
        $france = CountryQuery::create()->findOneByIsoalpha2('FR');
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($france);
        self::assertNotNull($belgium);

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $belgium, null, ['zipcode' => '1000', 'city' => 'Bruxelles']);
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]));

        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?')
            ->execute(['Acme', 'BE0123456789', (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme SPRL', $address->getId()]);
        $address->reload();

        $token = $this->authenticateAsCustomer($customer);
        $url = '/api/front/account/checkout/'.$cart->getId().'/invoice_address';

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        $cart->reload(true);
        self::assertTrue($this->getService(VatExemptionResolver::class)->isExemptedForCart($cart));

        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `vat_verified_at` = NULL, `vat_verified_name` = NULL WHERE `id` = ?')
            ->execute([$address->getId()]);
        AddressTableMap::clearInstancePool();

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        CartTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();
        $cart->reload(true);

        $copy = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());
        self::assertNotNull($copy);
        self::assertNull($copy->getVatVerifiedAt());
        self::assertFalse($this->getService(VatExemptionResolver::class)->isExemptedForCart($cart));
    }

    public function testChoosingAgainAnAddressVerifiedAgainCarriesTheNewVerificationToTheCart(): void
    {
        $france = CountryQuery::create()->findOneByIsoalpha2('FR');
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($france);
        self::assertNotNull($belgium);

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $belgium, null, ['zipcode' => '1000', 'city' => 'Bruxelles']);
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]));

        $verify = $this->getPropelConnection()->prepare(
            'UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?'
        );
        $verify->execute(['Acme', 'BE0123456789', (new \DateTime('-20 days'))->format('Y-m-d H:i:s'), 'Acme SPRL', $address->getId()]);
        AddressTableMap::clearInstancePool();

        $token = $this->authenticateAsCustomer($customer);
        $url = '/api/front/account/checkout/'.$cart->getId().'/invoice_address';
        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        $verify->execute(['Acme', 'BE0123456789', (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme Renamed SPRL', $address->getId()]);
        AddressTableMap::clearInstancePool();
        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        CartTableMap::clearInstancePool();
        CartAddressTableMap::clearInstancePool();
        $cart->reload(true);
        $copy = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());
        self::assertNotNull($copy);
        self::assertSame('Acme Renamed SPRL', $copy->getVatVerifiedName());
        self::assertGreaterThan(new \DateTime('-2 days'), $copy->getVatVerifiedAt());
    }

    public function testChoosingAgainAnAddressWhoseVerificationExpiredAsksForANewOne(): void
    {
        $lifetime = ConfigQuery::getVatVerificationLifetimeDays();

        self::assertSame(1, $this->invoiceSelectionsDispatchedWhenChosenTwice(new \DateTime(\sprintf('-%d days', $lifetime + 5))));
    }

    public function testChoosingAgainAnAddressNobodyCouldVerifyAsksForANewOne(): void
    {
        self::assertSame(1, $this->invoiceSelectionsDispatchedWhenChosenTwice(null));
    }

    public function testChoosingAgainAnAddressStillVerifiedChangesNothing(): void
    {
        self::assertSame(0, $this->invoiceSelectionsDispatchedWhenChosenTwice(new \DateTime('-1 day')));
    }

    private function invoiceSelectionsDispatchedWhenChosenTwice(?\DateTime $verifiedAt): int
    {
        $france = CountryQuery::create()->findOneByIsoalpha2('FR');
        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($france);
        self::assertNotNull($belgium);

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $address = $factory->address($customer, $belgium, null, ['zipcode' => '1000', 'city' => 'Bruxelles']);
        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]));

        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ? WHERE `id` = ?')
            ->execute(['Acme', 'BE0123456789', $verifiedAt?->format('Y-m-d H:i:s'), null === $verifiedAt ? null : 'Acme SPRL', $address->getId()]);
        AddressTableMap::clearInstancePool();

        $token = $this->authenticateAsCustomer($customer);
        $url = '/api/front/account/checkout/'.$cart->getId().'/invoice_address';

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        $dispatched = 0;
        $this->getService('event_dispatcher')->addListener(
            TheliaEvents::CART_SET_INVOICE_ADDRESS,
            static function () use (&$dispatched): void {
                ++$dispatched;
            },
            1000,
        );

        self::assertJsonResponseSuccessful($this->jsonRequest('POST', $url, ['addressId' => $address->getId()], token: $token));

        return $dispatched;
    }
}
