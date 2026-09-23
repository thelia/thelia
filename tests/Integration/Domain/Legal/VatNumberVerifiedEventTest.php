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
use Thelia\Domain\Legal\Service\NullVatNumberVerifier;
use Thelia\Domain\Legal\Service\VatNumberVerifierInterface;
use Thelia\Domain\Legal\VatVerificationResult;
use Thelia\Model\Address;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The only way the verification state is ever written.
 *
 * A module reports what an authority answered and Thelia records it, so the
 * rule that nothing else may set those columns holds in one place. A refusal
 * and an unreachable service both clear the state: the first means the number
 * stopped verifying, the second means nobody knows any more.
 */
final class VatNumberVerifiedEventTest extends ActionIntegrationTestCase
{
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

    public function testAnUnreachableServiceAlsoClearsIt(): void
    {
        $address = $this->verifiedAddress();

        $this->dispatch(
            new VatNumberVerifiedEvent($address, VatVerificationResult::undetermined()),
            TheliaEvents::VAT_NUMBER_VERIFIED,
        );

        $address->reload();
        self::assertNull($address->getVatVerifiedAt());
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

    private function address(): Address
    {
        $factory = $this->createFixtureFactory();

        return $factory->address($factory->customer($factory->customerTitle()));
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
