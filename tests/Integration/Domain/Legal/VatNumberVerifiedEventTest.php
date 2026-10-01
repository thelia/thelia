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

namespace Thelia\Tests\Integration\Domain\Legal;

use Thelia\Core\Event\Legal\VatNumberVerifiedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Domain\Legal\Service\NullVatNumberVerifier;
use Thelia\Domain\Legal\Service\VatNumberVerifierInterface;
use Thelia\Domain\Legal\VatVerificationResult;
use Thelia\Domain\Taxation\Enum\VatExemptionMode;
use Thelia\Domain\Taxation\Service\VatExemptionResolver;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\CartAddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CartAddressTableMap;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The only way the verification state is ever written.
 *
 * A module reports what an authority answered and Thelia records it, so the
 * rule that nothing else may set those columns holds in one place. A refusal
 * clears the state, an unreachable service leaves it as it was.
 */
final class VatNumberVerifiedEventTest extends ActionIntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        Country::resetDefaultCountryCache();

        parent::tearDown();
    }

    public function testAVerifiedAnswerIsRecordedOnTheAddress(): void
    {
        $address = $this->address();
        $verifiedAt = new \DateTimeImmutable('2026-03-01 09:30:00');

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified($verifiedAt, 'Acme SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertSame('2026-03-01 09:30:00', $address->getVatVerifiedAt('Y-m-d H:i:s'));
        self::assertSame('Acme SPRL', $address->getVatVerifiedName());
    }

    public function testARefusalClearsAPreviousAnswer(): void
    {
        $address = $this->verifiedAddress();

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::refused(new \DateTimeImmutable())),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertNull($address->getVatVerifiedAt(), 'A number that stopped verifying must stop exempting.');
        self::assertNull($address->getVatVerifiedName());
    }

    public function testAnUnreachableServiceLeavesAValidVerificationStanding(): void
    {
        $address = $this->verifiedAddress();
        $address->reload();
        $verifiedAt = $address->getVatVerifiedAt('Y-m-d H:i:s');
        self::assertNotNull($verifiedAt, 'Control: the address starts verified.');

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::undetermined()),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertSame($verifiedAt, $address->getVatVerifiedAt('Y-m-d H:i:s'), 'An outage told nothing about the number.');
        self::assertNotNull($address->getVatVerifiedName());
    }

    public function testAnUnreachableServiceGrantsNothing(): void
    {
        $address = $this->verifiedAddress();
        AddressQuery::create()->filterById($address->getId())->update(['VatVerifiedAt' => null, 'VatVerifiedName' => null]);
        $address->reload();

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::undetermined()),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertNull($address->getVatVerifiedAt());
    }

    public function testAnAnswerForAnAddressWithoutNumberIsNotRecorded(): void
    {
        $address = $this->verifiedAddress();
        AddressQuery::create()->filterById($address->getId())->update(['VatNumber' => null, 'VatVerifiedAt' => null, 'VatVerifiedName' => null]);
        $address->reload();

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified(new \DateTimeImmutable(), 'ACME')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertNull($address->getVatVerifiedAt(), 'An address with no VAT number has nothing a verification could confirm.');
    }

    public function testTheShippedVerifierAnswersNothingSoNoAddressIsEverVerified(): void
    {
        $verifier = $this->getService(VatNumberVerifierInterface::class);

        // A verification module is entitled to alias the contract, and then the
        // premise of this test no longer holds: what is pinned here is the wiring
        // of a Thelia that ships alone.
        if (!$verifier instanceof NullVatNumberVerifier) {
            self::markTestSkipped(\sprintf('A module took the contract over with %s.', $verifier::class));
        }

        $result = $verifier->verify('BE0123456789', 'BE');

        self::assertFalse($result->isVerified(), 'Without a verification module installed, nobody is exempt.');
        self::assertNull($result->verifiedAt);
    }

    public function testAnAnswerAboutANumberReplacedMeanwhileIsNotRecorded(): void
    {
        $address = $this->address();
        $address->setCompany('Acme')->setVatNumber('BE0123456789')->save($this->getPropelConnection());
        $heldByTheVerification = $this->loadedAgain($address);

        $this->loadedAgain($address)->setVatNumber('BE0987654321')->save($this->getPropelConnection());

        $this->dispatch(
            new VatNumberVerifiedEvent($heldByTheVerification, VatVerificationResult::verified(new \DateTimeImmutable(), 'Acme SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $row = $this->loadedAgain($address);
        self::assertSame('BE0987654321', $row->getVatNumber());
        self::assertNull($row->getVatVerifiedAt());
        self::assertNull($row->getVatVerifiedName());
    }

    public function testAnAnswerAboutAnAddressMovedMeanwhileIsNotRecorded(): void
    {
        $factory = $this->createFixtureFactory();
        $germany = $factory->country(['isocode' => 'DE', 'isoalpha2' => 'DE', 'isoalpha3' => 'DEX']);
        $address = $this->address();
        $address->setCompany('Acme')->setVatNumber('BE0123456789')->save($this->getPropelConnection());
        $heldByTheVerification = $this->loadedAgain($address);

        $this->loadedAgain($address)->setCountryId($germany->getId())->save($this->getPropelConnection());

        $this->dispatch(
            new VatNumberVerifiedEvent($heldByTheVerification, VatVerificationResult::verified(new \DateTimeImmutable(), 'Acme SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $row = $this->loadedAgain($address);
        self::assertSame($germany->getId(), $row->getCountryId());
        self::assertNull($row->getVatVerifiedAt());
    }

    public function testTheRecordedAnswerIsVisibleOnTheInstanceThatAskedForIt(): void
    {
        $address = $this->address();
        $address->setCompany('Acme')->setVatNumber('BE0123456789')->save($this->getPropelConnection());

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified(new \DateTimeImmutable('2026-03-01 09:30:00'), 'Acme SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        self::assertSame('2026-03-01 09:30:00', $address->getVatVerifiedAt('Y-m-d H:i:s'));
        self::assertSame('Acme SPRL', $address->getVatVerifiedName());
        self::assertFalse($address->isModified());
    }

    public function testARefusalAfterTheAddressWasChosenStopsTheExemptionOfTheCart(): void
    {
        [$address, $cart] = $this->cartBilledToABelgianAddress(new \DateTime('-1 day'));
        self::assertTrue($this->getService(VatExemptionResolver::class)->isExemptedForCart($cart));

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::refused(new \DateTimeImmutable())),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $copy = $this->invoiceCopyOf($cart);
        self::assertNull($copy->getVatVerifiedAt());
        self::assertNull($copy->getVatVerifiedName());
        self::assertFalse($this->getService(VatExemptionResolver::class)->isExemptedForCart($this->reloadedCart($cart)));
    }

    public function testAVerificationAfterTheAddressWasChosenReachesTheCart(): void
    {
        [$address, $cart] = $this->cartBilledToABelgianAddress(null);
        self::assertFalse($this->getService(VatExemptionResolver::class)->isExemptedForCart($cart));

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified(new \DateTimeImmutable('-1 hour'), 'Acme SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        self::assertSame('Acme SPRL', $this->invoiceCopyOf($cart)->getVatVerifiedName());
        self::assertTrue($this->getService(VatExemptionResolver::class)->isExemptedForCart($this->reloadedCart($cart)));
    }

    public function testACopyFollowsTheNewNumberOfItsAddressAndOnlyItsVerification(): void
    {
        [$address, $cart] = $this->cartBilledToABelgianAddress(new \DateTime('-1 day'));
        $address->setVatNumber('BE0987654321')->save($this->getPropelConnection());

        $copy = $this->invoiceCopyOf($cart);
        self::assertSame('BE0987654321', $copy->getVatNumber());
        self::assertNull($copy->getVatVerifiedAt(), 'The verification of the previous number does not follow.');

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::verified(new \DateTimeImmutable(), 'Other SPRL')),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        self::assertSame('Other SPRL', $this->invoiceCopyOf($cart)->getVatVerifiedName());
    }

    /**
     * @return array{Address, Cart}
     */
    private function cartBilledToABelgianAddress(?\DateTime $verifiedAt): array
    {
        $factory = $this->createFixtureFactory();
        $france = $factory->country(['isocode' => 'FR', 'isoalpha2' => 'FR', 'isoalpha3' => 'FRX']);
        $belgium = $factory->country(['isocode' => 'BE', 'isoalpha2' => 'BE', 'isoalpha3' => 'BEX']);
        ConfigQuery::write(VatExemptionMode::CONFIG_KEY, VatExemptionMode::VERIFIED_VAT_NUMBER->value);
        ConfigQuery::write('store_vat_exempt', '0');
        ConfigQuery::write('store_country', (string) $france->getId());

        $customer = $factory->customer($factory->customerTitle());
        $address = $factory->address($customer, $belgium);
        $address
            ->setCompany('Acme')
            ->setVatNumber('BE0123456789')
            ->setVatVerifiedAt($verifiedAt)
            ->setVatVerifiedName(null === $verifiedAt ? null : 'Acme SPRL')
            ->save($this->getPropelConnection());

        $cart = $factory->cart($customer);
        $copy = $this->getService(CartAddressService::class)->getOrCreateCartAddressFromAddress($address, null);
        $cart->setAddressInvoiceId($copy->getId())->save($this->getPropelConnection());

        return [$address, $cart];
    }

    private function invoiceCopyOf(Cart $cart): CartAddress
    {
        CartAddressTableMap::clearInstancePool();
        $copy = CartAddressQuery::create()->findPk($cart->getAddressInvoiceId());
        self::assertNotNull($copy);

        return $copy;
    }

    private function reloadedCart(Cart $cart): Cart
    {
        $cart->clearAllReferences();
        $cart->reload(true);

        return $cart;
    }

    private function loadedAgain(Address $address): Address
    {
        AddressTableMap::clearInstancePool();
        $loaded = AddressQuery::create()->findPk($address->getId());
        self::assertNotNull($loaded);

        return $loaded;
    }

    private function address(): Address
    {
        $factory = $this->createFixtureFactory();
        $address = $factory->address($factory->customer($factory->customerTitle()));
        $address->setCompany('Acme')->setVatNumber('BE0123456789')->save($this->getPropelConnection());

        return $address;
    }

    private function verifiedAddress(): Address
    {
        $address = $this->address();
        $address
            ->setVatVerifiedAt(new \DateTime('-1 day'))
            ->setVatVerifiedName('Acme SPRL')
            ->save($this->getPropelConnection());

        return $address;
    }
}
