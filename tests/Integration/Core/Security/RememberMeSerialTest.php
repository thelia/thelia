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

namespace Thelia\Tests\Integration\Core\Security;

use Thelia\Core\Security\Token\TokenProvider;
use Thelia\Model\AdminQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\AdminTableMap;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * An account created outside the application (an import, a migration) has no remember-me
 * serial, and ticking "Remember me" at its first login must not end in a 500.
 */
final class RememberMeSerialTest extends IntegrationTestCase
{
    public function testACustomerWithoutSerialGetsOneThatIsStoredAndStable(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setRememberMeSerial(null)->save($this->getPropelConnection());
        self::assertNull($customer->getRememberMeSerial());

        $serial = $customer->getSerial();

        self::assertNotSame('', $serial);
        self::assertSame($serial, $customer->getSerial(), 'The serial must not change from one call to the next.');

        CustomerTableMap::clearInstancePool();
        self::assertSame($serial, CustomerQuery::create()->findPk($customer->getId())?->getRememberMeSerial());
    }

    public function testTheRememberMeKeyOfACustomerWithoutSerialIsBuilt(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $customer->setRememberMeSerial(null)->save($this->getPropelConnection());

        $key = (new TokenProvider())->encodeKey($customer);

        $decoded = (new TokenProvider())->decodeKey($key);
        self::assertSame($customer->getSerial(), $decoded['serial']);
        self::assertNotSame('', $decoded['serial']);
    }

    public function testAnAdminWithoutSerialGetsOneThatIsStoredAndStable(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $admin->setRememberMeSerial(null)->save($this->getPropelConnection());
        self::assertNull($admin->getRememberMeSerial());

        $serial = $admin->getSerial();

        self::assertNotSame('', $serial);
        self::assertSame($serial, $admin->getSerial(), 'The serial must not change from one call to the next.');

        AdminTableMap::clearInstancePool();
        self::assertSame($serial, AdminQuery::create()->findPk($admin->getId())?->getRememberMeSerial());
    }
}
