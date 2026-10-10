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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
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

    public function testAnAdministratorAuthenticatedByTokenIsTheAuthorWithoutABackOfficeSession(): void
    {
        $admin = new Admin();
        $admin->setId(9)->setLogin('api-admin');

        $actor = $this->resolverFor(null, null, $admin)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('api-admin', $actor->label);
        self::assertSame(9, $actor->adminId);
    }

    public function testACustomerTokenNeverMakesAnAdministratorAndLeavesTheModuleTheAuthor(): void
    {
        $tokenCustomer = new Customer();
        $tokenCustomer->setRef('CUS-7');

        $actor = $this->resolverFor(null, null, $tokenCustomer)->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
        self::assertNull($actor->adminId);
    }

    public function testTheAdministratorOfTheTokenWinsOverABackOfficeSessionOfTheSameBrowser(): void
    {
        $sessionAdmin = new Admin();
        $sessionAdmin->setId(7)->setLogin('alice');
        $tokenAdmin = new Admin();
        $tokenAdmin->setId(9)->setLogin('api-admin');

        $actor = $this->resolverFor($sessionAdmin, null, $tokenAdmin, '/api/admin/orders/12/capture')->resolve();

        self::assertSame('api-admin', $actor->label);
        self::assertSame(9, $actor->adminId);
    }

    public function testAModuleReportingOutsideTheBackOfficeIsTheAuthorEvenWithAnAdministratorInSession(): void
    {
        // The provider's return lands in a browser where an administrator is also signed
        // in to the back office: the module reported the movement, not the administrator.
        $admin = new Admin();
        $admin->setId(7)->setLogin('alice');

        $actor = $this->resolverFor($admin, null, null, '/payment/paybox/return')->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::MODULE, $actor->actorType);
        self::assertSame('Paybox', $actor->label);
    }

    public function testABackOfficeGestureThroughAModuleStaysTheAdministrators(): void
    {
        $admin = new Admin();
        $admin->setId(7)->setLogin('alice');

        $actor = $this->resolverFor($admin, null, null, '/admin/order/update/12/payment-capture')->resolve('Paybox');

        self::assertSame(OrderHistoryActorType::ADMIN, $actor->actorType);
        self::assertSame('alice', $actor->label);
    }

    private function resolverFor(?Admin $admin, ?Customer $customer, ?UserInterface $tokenUser = null, ?string $path = null): OrderHistoryActorResolver
    {
        $securityContext = $this->createMock(SecurityContext::class);
        $securityContext->method('getAdminUser')->willReturn($admin);
        $securityContext->method('getCustomerUser')->willReturn($customer);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($tokenUser);

        $requestStack = new RequestStack();

        if (null !== $path) {
            $requestStack->push(Request::create($path));
        }

        return new OrderHistoryActorResolver($securityContext, $security, $requestStack);
    }
}
