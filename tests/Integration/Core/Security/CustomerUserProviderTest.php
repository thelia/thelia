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

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Thelia\Core\Security\UserProvider\CustomerUserProvider;
use Thelia\Model\Admin;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

final class CustomerUserProviderTest extends IntegrationTestCase
{
    private CustomerUserProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new CustomerUserProvider();
    }

    public function testLoadUserByEmail(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['email' => 'custprovider@test.com']);
        $loaded = $this->provider->loadUserByIdentifier('custprovider@test.com');

        self::assertInstanceOf(Customer::class, $loaded);
        self::assertSame($customer->getId(), $loaded->getId());
    }

    public function testLoadUserThrowsForUnknownEmail(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('no-such-customer@test.com');
    }

    public function testRefreshUserReloadsFromDatabase(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $refreshed = $this->provider->refreshUser($customer);

        self::assertInstanceOf(Customer::class, $refreshed);
        self::assertSame($customer->getEmail(), $refreshed->getEmail());
    }

    public function testRefreshUserThrowsForNonCustomerUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $admin = $this->createFixtureFactory()->admin();
        $this->provider->refreshUser($admin);
    }

    public function testSupportsCustomerClass(): void
    {
        self::assertTrue($this->provider->supportsClass(Customer::class));
        self::assertFalse($this->provider->supportsClass(Admin::class));
    }

    public function testLoadByIdentifierAndPayloadWithCustomerType(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['email' => 'jwt_customer@test.com']);

        $loaded = $this->provider->loadUserByIdentifierAndPayload(
            'jwt_customer@test.com',
            ['type' => Customer::class],
        );

        self::assertInstanceOf(Customer::class, $loaded);
        self::assertSame($customer->getId(), $loaded->getId());
    }

    public function testLoadByIdentifierAndPayloadThrowsForWrongType(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->provider->loadUserByIdentifierAndPayload(
            'anyone@test.com',
            ['type' => Admin::class],
        );
    }
}
