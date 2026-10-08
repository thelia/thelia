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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\Address;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class AddressVatVerificationValidTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testANeverVerifiedAddressIsNotValid(): void
    {
        self::assertFalse($this->address()->getVatVerificationValid());
    }

    public function testARecentVerificationIsValid(): void
    {
        $address = $this->address();
        $address->setVatVerifiedAt(new \DateTime('-10 days'))->save($this->getPropelConnection());

        self::assertTrue($address->getVatVerificationValid());
    }

    public function testAVerificationOlderThanItsLifetimeIsNotValid(): void
    {
        $address = $this->address();
        $address->setVatVerifiedAt(new \DateTime('-91 days'))->save($this->getPropelConnection());

        self::assertFalse($address->getVatVerificationValid());
    }

    public function testTheConfiguredLifetimeDecidesWhenAVerificationExpires(): void
    {
        ConfigQuery::write('vat_verification_lifetime_days', '30');

        $address = $this->address();
        $address->setVatVerifiedAt(new \DateTime('-29 days'))->save($this->getPropelConnection());
        self::assertTrue($address->getVatVerificationValid());

        $address->setVatVerifiedAt(new \DateTime('-31 days'))->save($this->getPropelConnection());
        self::assertFalse($address->getVatVerificationValid());
    }

    #[DataProvider('unusableLifetimes')]
    public function testAnUnusableLifetimeFallsBackToTheDefaultInsteadOfNeverExpiring(string $configured): void
    {
        ConfigQuery::write('vat_verification_lifetime_days', $configured);

        self::assertSame(ConfigQuery::DEFAULT_VAT_VERIFICATION_LIFETIME_DAYS, ConfigQuery::getVatVerificationLifetimeDays());

        $address = $this->address();
        $address->setVatVerifiedAt(new \DateTime('-'.(ConfigQuery::DEFAULT_VAT_VERIFICATION_LIFETIME_DAYS + 1).' days'))->save($this->getPropelConnection());

        self::assertFalse($address->getVatVerificationValid(), 'A lifetime of "'.$configured.'" kept an old verification exempting.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableLifetimes(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'not a number' => ['abc'];
        yield 'empty' => [''];
    }

    private function address(): Address
    {
        $factory = $this->createFixtureFactory();

        return $factory->address($factory->customer($factory->customerTitle()));
    }
}
