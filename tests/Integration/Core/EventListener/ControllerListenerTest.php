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

namespace Thelia\Tests\Integration\Core\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\EventListener\ControllerListener;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\Security\SecurityContext;
use Thelia\Exception\AdminAccessDenied;
use Thelia\Test\IntegrationTestCase;

final class ControllerListenerTest extends IntegrationTestCase
{
    private ControllerListener $listener;
    private SecurityContext $securityContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityContext = $this->getService(SecurityContext::class);
        $this->listener = new ControllerListener($this->securityContext);
    }

    private function createAdminControllerCallable(): array
    {
        $controller = new class extends BaseAdminController {
            public function handleAction(): void
            {
            }
        };

        return [$controller, 'handleAction'];
    }

    public function testAdminFirewallThrowsWhenNoAdminLoggedIn(): void
    {
        $this->securityContext->clearAdminUser();

        $request = TheliaRequest::create('/admin/catalog');

        $event = new ControllerEvent(
            self::$kernel,
            $this->createAdminControllerCallable(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectException(AdminAccessDenied::class);
        $this->listener->adminFirewall($event);
    }

    public function testAdminFirewallAllowsWhenAdminLoggedIn(): void
    {
        $admin = $this->createFixtureFactory()->admin();
        $this->securityContext->setAdminUser($admin);

        $request = TheliaRequest::create('/admin/catalog');

        $event = new ControllerEvent(
            self::$kernel,
            $this->createAdminControllerCallable(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->listener->adminFirewall($event);
        self::assertTrue(true);
    }

    public function testAdminFirewallIgnoresNonAdminControllers(): void
    {
        $this->securityContext->clearAdminUser();

        $request = TheliaRequest::create('/some/route');

        $event = new ControllerEvent(
            self::$kernel,
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->listener->adminFirewall($event);
        self::assertTrue(true);
    }

    public function testAdminFirewallIgnoresNotLoggedAttribute(): void
    {
        $this->securityContext->clearAdminUser();

        $request = TheliaRequest::create('/admin/login');
        $request->attributes->set('not-logged', '1');

        $event = new ControllerEvent(
            self::$kernel,
            $this->createAdminControllerCallable(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->listener->adminFirewall($event);
        self::assertTrue(true);
    }
}
