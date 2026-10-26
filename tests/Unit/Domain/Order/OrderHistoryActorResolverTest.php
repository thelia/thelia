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

namespace Thelia\Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\Enum\OrderHistoryActorType;
use Thelia\Domain\Order\Service\OrderHistoryActorResolver;
use Thelia\Model\Admin;
use Thelia\Model\Customer;

final class OrderHistoryActorResolverTest extends TestCase
{
    public function testTheSignedInAdminWinsOverEveryOtherCandidate(): void
    {
        $admin = new Admin();
        $admin->setId(7)->setLogin('alice');

        $customer = new Customer();
        $customer->setRef('CUS-1');

        $actor = $this->resolverFor($admin, $customer)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('alice', $actor->label);
        self::assertSame(7, $actor->adminId);
    }

    public function testTheSignedInCustomerIsNamedByReferenceAndNotByEmail(): void
    {
        $customer = new Customer();
        $customer->setRef('CUS-42')->setEmail('buyer@example.com');

        $actor = $this->resolverFor(null, $customer)->resolve();

        self::assertSame(OrderHistoryActorType::CUSTOMER, $actor->actorType);
        self::assertSame('CUS-42', $actor->label);
        self::assertNull($actor->adminId);
    }

    public function testAModuleIsTheAuthorWhenNobodyIsSignedIn(): void
    {
        $actor = $this->resolverFor(null, null)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
        self::assertNull($actor->adminId);
    }

    public function testAModuleExplicitlyProvidedWinsOverTheCustomerInSession(): void
    {
        $customer = new Customer();
        $customer->setRef('CUS-42');

        $actor = $this->resolverFor(null, $customer)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
        self::assertNull($actor->adminId);
    }

    public function testWithoutSessionNorModuleTheAuthorIsTheSystemAndCarriesNoLabel(): void
    {
        $actor = $this->resolverFor(null, null)->resolve();

        self::assertSame(OrderHistoryActorType::SYSTEM, $actor->actorType);
        self::assertNull($actor->label);
        self::assertNull($actor->adminId);
    }

    public function testTheAdminOfTheTokenIsTheAuthorWhenNoSessionNamesAnyone(): void
    {
        $admin = new Admin();
        $admin->setId(9)->setLogin('bob');

        $actor = $this->resolverFor(null, null, $admin)->resolve();

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('bob', $actor->label);
        self::assertSame(9, $actor->adminId);
    }

    public function testTheCustomerOfTheTokenIsTheAuthorWhenNoSessionNamesAnyone(): void
    {
        $customer = new Customer();
        $customer->setRef('CUS-77')->setEmail('api@example.com');

        $actor = $this->resolverFor(null, null, $customer)->resolve();

        self::assertSame(OrderHistoryActorType::CUSTOMER, $actor->actorType);
        self::assertSame('CUS-77', $actor->label);
    }

    public function testAModuleExplicitlyProvidedWinsOverTheCustomerOfTheToken(): void
    {
        $customer = new Customer();
        $customer->setRef('CUS-77');

        $actor = $this->resolverFor(null, null, $customer)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
    }

    public function testTheAdminOfTheTokenWinsOverAModuleLikeTheAdminInSession(): void
    {
        $admin = new Admin();
        $admin->setId(9)->setLogin('bob');

        $actor = $this->resolverFor(null, null, $admin)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('bob', $actor->label);
    }

    public function testTheAdminOfTheTokenWinsOverTheAdminInSession(): void
    {
        $sessionAdmin = new Admin();
        $sessionAdmin->setId(7)->setLogin('alice');
        $tokenAdmin = new Admin();
        $tokenAdmin->setId(9)->setLogin('bob');

        $actor = $this->resolverFor($sessionAdmin, null, $tokenAdmin)->resolve();

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('bob', $actor->label);
        self::assertSame(9, $actor->adminId);
    }

    public function testTheCustomerOfTheTokenWinsOverTheAdminInSession(): void
    {
        $sessionAdmin = new Admin();
        $sessionAdmin->setId(7)->setLogin('alice');
        $tokenCustomer = new Customer();
        $tokenCustomer->setRef('CUS-77');

        $actor = $this->resolverFor($sessionAdmin, null, $tokenCustomer)->resolve();

        self::assertSame(OrderHistoryActorType::CUSTOMER, $actor->actorType);
        self::assertSame('CUS-77', $actor->label);
        self::assertNull($actor->adminId);
    }

    public function testAModuleExplicitlyProvidedWinsOverTheCustomerOfTheTokenEvenWithAnAdminInSession(): void
    {
        $sessionAdmin = new Admin();
        $sessionAdmin->setId(7)->setLogin('alice');
        $tokenCustomer = new Customer();
        $tokenCustomer->setRef('CUS-77');

        $actor = $this->resolverFor($sessionAdmin, null, $tokenCustomer)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
    }

    public function testTheAdminOfTheTokenWinsOverTheCustomerInSession(): void
    {
        $sessionCustomer = new Customer();
        $sessionCustomer->setRef('CUS-1');
        $tokenAdmin = new Admin();
        $tokenAdmin->setId(9)->setLogin('bob');

        $actor = $this->resolverFor(null, $sessionCustomer, $tokenAdmin)->resolve();

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('bob', $actor->label);
    }

    public function testTheCustomerOfTheTokenWinsOverTheCustomerInSession(): void
    {
        $sessionCustomer = new Customer();
        $sessionCustomer->setRef('CUS-1');
        $tokenCustomer = new Customer();
        $tokenCustomer->setRef('CUS-77');

        $actor = $this->resolverFor(null, $sessionCustomer, $tokenCustomer)->resolve();

        self::assertSame(OrderHistoryActorType::CUSTOMER, $actor->actorType);
        self::assertSame('CUS-77', $actor->label);
    }

    public function testAUserOfTheTokenThatIsNeitherAnAdminNorACustomerLeavesTheSessionAnswer(): void
    {
        $sessionAdmin = new Admin();
        $sessionAdmin->setId(7)->setLogin('alice');

        $actor = $this->resolverFor($sessionAdmin, null, new InMemoryUser('someone', null))->resolve();

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('alice', $actor->label);
    }

    public function testAUserOfTheTokenThatIsNeitherAnAdminNorACustomerNamesNobodyWithoutSession(): void
    {
        $actor = $this->resolverFor(null, null, new InMemoryUser('someone', null))->resolve();

        self::assertSame(OrderHistoryActorType::SYSTEM, $actor->actorType);
        self::assertNull($actor->label);
    }

    public function testWithoutAnyTokenNorSessionTheAuthorIsTheSystem(): void
    {
        $actor = $this->resolverFor(null, null, null)->resolve();

        self::assertSame(OrderHistoryActorType::SYSTEM, $actor->actorType);
        self::assertNull($actor->label);
        self::assertNull($actor->adminId);
    }

    private function resolverFor(?Admin $admin, ?Customer $customer, ?SecurityUserInterface $tokenUser = null): OrderHistoryActorResolver
    {
        $securityContext = $this->createMock(SecurityContext::class);
        $securityContext->method('getAdminUser')->willReturn($admin);
        $securityContext->method('getCustomerUser')->willReturn($customer);

        $tokenStorage = new TokenStorage();

        if (null !== $tokenUser) {
            $tokenStorage->setToken(new UsernamePasswordToken($tokenUser, 'api', $tokenUser->getRoles()));
        }

        return new OrderHistoryActorResolver($securityContext, $tokenStorage);
    }
}
