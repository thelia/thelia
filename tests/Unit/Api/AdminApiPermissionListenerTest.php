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

namespace Thelia\Tests\Unit\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Thelia\Api\EventListener\AdminApiPermissionListener;
use Thelia\Api\Security\AdminApiResourcePermissions;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;

/**
 * The listener refuses the admin API to whoever is not an administrator by itself,
 * instead of leaning on the ROLE_ADMIN rule of the firewall: a firewall rule edited,
 * reordered or shadowed by a module must not open the admin API to a customer token.
 */
final class AdminApiPermissionListenerTest extends TestCase
{
    public function testACustomerTokenIsRefusedEvenIfTheFirewallLetItThrough(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->listenerFor(new Customer())($this->eventFor('/api/admin/orders/12/capture'));
    }

    public function testARequestWithoutUserIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->listenerFor(null)($this->eventFor('/api/admin/orders'));
    }

    public function testTheLoginRouteStaysOpen(): void
    {
        $this->listenerFor(null)($this->eventFor('/api/admin/login'));

        $this->addToAssertionCount(1);
    }

    public function testAFrontRouteIsNotTheListenersBusiness(): void
    {
        $this->listenerFor(new Customer())($this->eventFor('/api/front/account/orders'));

        $this->addToAssertionCount(1);
    }

    private function listenerFor(?UserInterface $user): AdminApiPermissionListener
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new AdminApiPermissionListener(
            $security,
            $this->createMock(SecurityContext::class),
            new AdminApiResourcePermissions(),
            $this->createMock(ResourceMetadataCollectionFactoryInterface::class),
        );
    }

    private function eventFor(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path, 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
