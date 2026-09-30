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

use Thelia\Core\Event\Address\AddressCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Test\ActionIntegrationTestCase;

final class AddressVatVerificationTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testChangingTheCountryDropsTheVerificationAndTheExemption(): void
    {
        $france = $this->countryOf('FR');
        $germany = $this->countryOf('DE');

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $address = $this->verifiedAddressIn($france, 'FR40303265045');

        $this->updateAddress($address, $germany, 'FR40303265045');

        $address->reload();
        self::assertSame($germany->getId(), $address->getCountryId());
        self::assertNull($address->getVatVerifiedAt());
        self::assertNull($address->getVatVerifiedName());

        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);

        $cart = $this->factory->cart();
        $cartAddress = $this->getService(CartAddressService::class)->getOrCreateCartAddressFromAddress($address, null);
        $cart->setAddressInvoiceId($cartAddress->getId())->save($this->getPropelConnection());

        self::assertFalse($this->getService(VatExemptionResolver::class)->isExemptedForCart($cart));
    }

    public function testChangingTheNumberDropsTheVerification(): void
    {
        $belgium = $this->countryOf('BE');
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);

        $address = $this->verifiedAddressIn($belgium, 'BE0123456789');

        $this->updateAddress($address, $belgium, 'BE0987654321');

        $address->reload();
        self::assertSame('BE0987654321', $address->getVatNumber());
        self::assertNull($address->getVatVerifiedAt());
        self::assertNull($address->getVatVerifiedName());
    }

    public function testEditingAnotherFieldKeepsTheVerification(): void
    {
        $belgium = $this->countryOf('BE');
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);

        $address = $this->verifiedAddressIn($belgium, 'BE0123456789');

        $this->updateAddress($address, $belgium, 'BE0123456789', 'Liège');

        $address->reload();
        self::assertSame('Liège', $address->getCity());
        self::assertNotNull($address->getVatVerifiedAt());
        self::assertSame('ACME SA', $address->getVatVerifiedName());
    }

    private function verifiedAddressIn(Country $country, string $vatNumber): Address
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $address = $this->factory->address($customer, $country);
        $address
            ->setCompany('Acme')
            ->setVatNumber($vatNumber)
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setVatVerifiedName('ACME SA')
            ->save($this->getPropelConnection());

        return $address;
    }

    private function updateAddress(Address $address, Country $country, string $vatNumber, ?string $city = null): void
    {
        $event = new AddressCreateOrUpdateEvent(
            (string) $address->getLabel(),
            $address->getTitleId(),
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
            (string) $address->getAddress1(),
            '',
            '',
            (string) $address->getZipcode(),
            $city ?? (string) $address->getCity(),
            $country->getId(),
            '',
            '',
            'Acme',
            0,
            null,
            null,
            $vatNumber,
        );
        $event->setAddress($address);

        $this->dispatch($event, TheliaEvents::ADDRESS_UPDATE);
    }

    private function countryOf(string $isoAlpha2): Country
    {
        return $this->factory->country([
            'isocode' => $isoAlpha2,
            'isoalpha2' => $isoAlpha2,
            'isoalpha3' => $isoAlpha2.'X',
        ]);
    }
}
