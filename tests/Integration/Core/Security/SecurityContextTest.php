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

use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;
use Thelia\Test\IntegrationTestCase;

final class SecurityContextTest extends IntegrationTestCase
{
    private SecurityContext $securityContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityContext = $this->getService(SecurityContext::class);
    }

    public function testNoUserLoggedInByDefault(): void
    {
        self::assertFalse($this->securityContext->hasAdminUser());
        self::assertFalse($this->securityContext->hasCustomerUser());
        self::assertFalse($this->securityContext->hasLoggedInUser());
    }

    public function testSetAndClearAdminUser(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->securityContext->setAdminUser($admin);

        self::assertTrue($this->securityContext->hasAdminUser());
        self::assertTrue($this->securityContext->hasLoggedInUser());
        self::assertInstanceOf(Admin::class, $this->securityContext->getAdminUser());

        $this->securityContext->clearAdminUser();
        self::assertFalse($this->securityContext->hasAdminUser());
    }

    public function testSetAndClearCustomerUser(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $this->securityContext->setCustomerUser($customer);

        self::assertTrue($this->securityContext->hasCustomerUser());
        self::assertTrue($this->securityContext->hasLoggedInUser());

        $this->securityContext->clearCustomerUser();
        self::assertFalse($this->securityContext->hasCustomerUser());
    }

    public function testHasRequiredRoleMatchesAdminRole(): void
    {
        $admin = $this->createFixtureFactory()->admin();

        self::assertTrue(
            $this->securityContext->hasRequiredRole($admin, ['ADMIN']),
        );
        self::assertFalse(
            $this->securityContext->hasRequiredRole($admin, ['SUPERADMIN_ONLY']),
        );
    }

    public function testCheckRoleFindsAdminOrCustomer(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->securityContext->setAdminUser($admin);

        $found = $this->securityContext->checkRole(['ADMIN']);
        self::assertNotNull($found);
        self::assertSame($admin->getLogin(), $found->getUsername());

        $this->securityContext->clearAdminUser();
        $notFound = $this->securityContext->checkRole(['ADMIN']);
        self::assertNull($notFound);
    }
}
