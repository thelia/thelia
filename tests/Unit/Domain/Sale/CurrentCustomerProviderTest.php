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

namespace Thelia\Tests\Unit\Domain\Sale;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Sale\CurrentCustomerProvider;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

final class CurrentCustomerProviderTest extends TestCase
{
    public function testTheSessionCustomerIsReturnedWhenNoTokenIsAuthenticated(): void
    {
        $sessionCustomer = $this->customer('session@example.com');

        $provider = new CurrentCustomerProvider($this->securityContext($sessionCustomer), new TokenStorage());

        self::assertSame($sessionCustomer, $provider->getCurrentCustomer());
    }

    public function testTheTokenCustomerIsReturnedWhenThereIsNoSession(): void
    {
        $tokenCustomer = $this->customer('token@example.com');

        $provider = new CurrentCustomerProvider($this->securityContext(null), $this->tokenStorage($tokenCustomer));

        self::assertSame($tokenCustomer, $provider->getCurrentCustomer());
    }

    public function testTheTokenCustomerWinsOverTheSessionCustomer(): void
    {
        $tokenCustomer = $this->customer('token@example.com');

        $provider = new CurrentCustomerProvider(
            $this->securityContext($this->customer('session@example.com')),
            $this->tokenStorage($tokenCustomer),
        );

        self::assertSame($tokenCustomer, $provider->getCurrentCustomer());
    }

    public function testAnAdminTokenLeavesTheSessionCustomerInPlace(): void
    {
        $sessionCustomer = $this->customer('session@example.com');
        $admin = (new Admin())->setLogin('admin');

        $provider = new CurrentCustomerProvider($this->securityContext($sessionCustomer), $this->tokenStorage($admin));

        self::assertSame($sessionCustomer, $provider->getCurrentCustomer());
    }

    public function testAGuestIsNeverReturned(): void
    {
        $guest = $this->customer('guest@example.com')->setIsGuest(1);

        $provider = new CurrentCustomerProvider($this->securityContext(null), $this->tokenStorage($guest));

        self::assertNull($provider->getCurrentCustomer());
    }

    private function customer(string $email): Customer
    {
        return (new Customer())->setEmail($email);
    }

    private function securityContext(?Customer $sessionCustomer): SecurityContext
    {
        $securityContext = $this->createStub(SecurityContext::class);
        $securityContext->method('getCustomerUser')->willReturn($sessionCustomer);

        return $securityContext;
    }

    private function tokenStorage(Customer|Admin $user): TokenStorage
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

        return $tokenStorage;
    }
}
