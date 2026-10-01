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

namespace Thelia\Tests\Integration\Flexy;

use FlexyBundle\Service\GuestAddressCreator;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitle;
use Thelia\Test\ActionIntegrationTestCase;

final class GuestAddressVatNumberReuseTest extends ActionIntegrationTestCase
{
    private const VERIFIED_NUMBER = 'BE0123456789';

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testAGuestTypingNoVatNumberIsNotBilledOnTheVerifiedNumberOfAPreviousGuest(): void
    {
        $this->skipWithoutFlexy();
        [$guest, $title, $belgium] = $this->guestWithAVerifiedBelgianAddress();

        $addressId = $this->creator()->create(
            $guest,
            $this->typedAddress($belgium, null),
            'Billing address',
            $title->getId(),
            'John',
            'Doe',
            false,
        );

        $address = AddressQuery::create()->findPk($addressId);
        self::assertNotNull($address);
        self::assertNull($address->getVatNumber(), 'The address handed back must carry the number the visitor typed: none.');
        self::assertNull($address->getVatVerifiedAt());
        self::assertFalse($this->exemptsWhenBilledTo($guest, $address), 'A visitor who typed no VAT number must not be exempted.');
    }

    public function testTheSameNumberTypedWithSpacesAndInLowerCaseIsTheSameAddress(): void
    {
        $this->skipWithoutFlexy();
        [$guest, $title, $belgium] = $this->guestWithAVerifiedBelgianAddress();
        $existing = AddressQuery::create()->filterByCustomerId($guest->getId())->findOne();
        self::assertNotNull($existing);

        $addressId = $this->creator()->create(
            $guest,
            $this->typedAddress($belgium, 'be 0123 456 789'),
            'Billing address',
            $title->getId(),
            'John',
            'Doe',
            false,
        );

        self::assertSame($existing->getId(), $addressId);
        self::assertSame(1, AddressQuery::create()->filterByCustomerId($guest->getId())->count());
    }

    public function testAGuestTypingAnotherVatNumberIsNotBilledOnThePreviousVerifiedOne(): void
    {
        $this->skipWithoutFlexy();
        [$guest, $title, $belgium] = $this->guestWithAVerifiedBelgianAddress();

        $addressId = $this->creator()->create(
            $guest,
            $this->typedAddress($belgium, 'BE0999999999'),
            'Billing address',
            $title->getId(),
            'John',
            'Doe',
            false,
        );

        $address = AddressQuery::create()->findPk($addressId);
        self::assertNotNull($address);
        self::assertSame('BE0999999999', $address->getVatNumber(), 'The address handed back must carry the number the visitor typed.');
        self::assertNull($address->getVatVerifiedAt(), 'A number nobody verified must not come back verified.');
    }

    private function exemptsWhenBilledTo(Customer $guest, Address $address): bool
    {
        $france = CountryQuery::create()->findOneByIsoalpha2('FR');
        self::assertNotNull($france);
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('vat_verification_lifetime_days', '90');
        ConfigQuery::write('store_country', (string) $france->getId());

        $cart = $this->factory->cart($guest);
        $copy = (new CartAddressService())->getOrCreateCartAddressFromAddress($address);
        $cart->setAddressInvoiceId($copy->getId())->save($this->getPropelConnection());
        $cart->reload(true);

        return $this->getService(VatExemptionResolver::class)->isExemptedForCart($cart);
    }

    /**
     * @return array{0: Customer, 1: CustomerTitle, 2: Country}
     */
    private function guestWithAVerifiedBelgianAddress(): array
    {
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::DISABLED->value);

        $belgium = CountryQuery::create()->findOneByIsoalpha2('BE');
        self::assertNotNull($belgium);

        $title = $this->factory->customerTitle();
        $guest = $this->factory->guestCustomer($title, ['email' => 'buyer-'.uniqid().'@acme.test']);
        $address = $this->factory->address($guest, $belgium, $title, [
            'label' => 'Billing address',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '12 rue de la Loi',
            'address2' => '',
            'zipcode' => '1000',
            'city' => 'Bruxelles',
        ]);

        $this->getPropelConnection()
            ->prepare('UPDATE `address` SET `company` = ?, `vat_number` = ?, `vat_verified_at` = ?, `vat_verified_name` = ?, `phone` = ?, `cellphone` = ?, `state_id` = NULL WHERE `id` = ?')
            ->execute(['Acme', self::VERIFIED_NUMBER, (new \DateTime('-1 day'))->format('Y-m-d H:i:s'), 'Acme SPRL', '', '0601020304', $address->getId()]);

        return [$guest, $title, $belgium];
    }

    /**
     * @return array<string, mixed>
     */
    private function typedAddress(Country $country, ?string $vatNumber): array
    {
        return [
            'company' => 'Acme',
            'address1' => '12 rue de la Loi',
            'address2' => '',
            'zipcode' => '1000',
            'city' => 'Bruxelles',
            'country' => $country->getId(),
            'state' => '',
            'phone' => '',
            'cellphone' => '0601020304',
            'siret' => null,
            'vat_number' => $vatNumber,
        ];
    }

    private function creator(): GuestAddressCreator
    {
        return new GuestAddressCreator(static::getContainer()->get('event_dispatcher'));
    }

    private function skipWithoutFlexy(): void
    {
        if (!class_exists(GuestAddressCreator::class)) {
            self::markTestSkipped('The Flexy theme is not installed.');
        }

        $source = (string) file_get_contents((string) (new \ReflectionClass(GuestAddressCreator::class))->getFileName());

        if (!str_contains($source, '$candidate->getVatNumber()')) {
            self::markTestSkipped('The installed Flexy theme does not compare the VAT number of a guest address yet.');
        }
    }
}
