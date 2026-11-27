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

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\Showcase\AdministratorShowcaseAccess;
use Thelia\Test\IntegrationTestCase;

final class AdministratorShowcaseAccessTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(ShowcaseAccessInterface::class)) {
            self::markTestSkipped('The component showcase is not installed.');
        }
    }

    public function testTheShowcaseIsOpenInDebugWithoutAnyone(): void
    {
        self::assertTrue($this->access(debug: true)->isGranted(Request::create('/_showcase')));
    }

    public function testTheShowcaseIsClosedToAVisitorOutsideDebug(): void
    {
        self::assertFalse($this->access(debug: false)->isGranted(Request::create('/_showcase')));
    }

    public function testTheShowcaseIsClosedToACustomerOutsideDebug(): void
    {
        $factory = $this->createFixtureFactory();
        $this->getService(SecurityContext::class)->setCustomerUser($factory->customer($factory->customerTitle()));

        self::assertFalse($this->access(debug: false)->isGranted(Request::create('/_showcase')));
    }

    public function testTheShowcaseIsOpenToAnAdministratorOutsideDebug(): void
    {
        $this->getService(SecurityContext::class)->setAdminUser($this->createFixtureFactory()->admin());

        self::assertTrue($this->access(debug: false)->isGranted(Request::create('/_showcase')));
    }

    private function access(bool $debug): AdministratorShowcaseAccess
    {
        return new AdministratorShowcaseAccess($this->getService(SecurityContext::class), $debug);
    }
}
