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
use Thelia\Core\Security\UserProvider\AdminUserProvider;
use Thelia\Model\Admin;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

final class AdminUserProviderTest extends IntegrationTestCase
{
    private AdminUserProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AdminUserProvider();
    }

    public function testLoadUserByLoginName(): void
    {
        $admin = $this->createFixtureFactory()->admin(['login' => 'test_admin_provider']);
        $loaded = $this->provider->loadUserByIdentifier('test_admin_provider');

        self::assertInstanceOf(Admin::class, $loaded);
        self::assertSame($admin->getId(), $loaded->getId());
    }

    public function testLoadUserByEmail(): void
    {
        $admin = $this->createFixtureFactory()->admin(['email' => 'provider@test.com']);
        $loaded = $this->provider->loadUserByIdentifier('provider@test.com');

        self::assertInstanceOf(Admin::class, $loaded);
        self::assertSame($admin->getId(), $loaded->getId());
    }

    public function testLoadUserThrowsForUnknownIdentifier(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->provider->loadUserByIdentifier('nonexistent_admin_login');
    }

    public function testRefreshUserReloadsFromDatabase(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $refreshed = $this->provider->refreshUser($admin);

        self::assertInstanceOf(Admin::class, $refreshed);
        self::assertSame($admin->getLogin(), $refreshed->getUserIdentifier());
    }

    public function testRefreshUserThrowsForNonAdminUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->provider->refreshUser($customer);
    }

    public function testSupportsAdminClass(): void
    {
        self::assertTrue($this->provider->supportsClass(Admin::class));
        self::assertFalse($this->provider->supportsClass(Customer::class));
    }

    public function testLoadByIdentifierAndPayloadWithAdminType(): void
    {
        $admin = $this->createFixtureFactory()->admin(['login' => 'jwt_admin_test']);
        $loaded = $this->provider->loadUserByIdentifierAndPayload(
            'jwt_admin_test',
            ['type' => Admin::class],
        );

        self::assertInstanceOf(Admin::class, $loaded);
        self::assertSame($admin->getId(), $loaded->getId());
    }

    public function testLoadByIdentifierAndPayloadThrowsForWrongType(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->provider->loadUserByIdentifierAndPayload(
            'anyone',
            ['type' => Customer::class],
        );
    }
}
