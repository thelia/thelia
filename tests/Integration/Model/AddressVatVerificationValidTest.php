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

    private function address(): Address
    {
        $factory = $this->createFixtureFactory();

        return $factory->address($factory->customer($factory->customerTitle()));
    }
}
