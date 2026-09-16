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

    private function resolverFor(?Admin $admin, ?Customer $customer): OrderHistoryActorResolver
    {
        $securityContext = $this->createMock(SecurityContext::class);
        $securityContext->method('getAdminUser')->willReturn($admin);
        $securityContext->method('getCustomerUser')->willReturn($customer);

        return new OrderHistoryActorResolver($securityContext);
    }
}
