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

namespace Thelia\Tests\Integration\Controller\Front;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Test\IntegrationTestCase;

/**
 * The router service and the route identifiers BaseFrontController redirects to are plain
 * strings, resolved only when a redirect is actually issued. Nothing reports a stale one
 * until a visitor walks into it, so they are pinned here against the running container.
 */
final class BaseFrontControllerTest extends IntegrationTestCase
{
    public function testARedirectToAFrontRouteGoesThroughARouterTheContainerProvides(): void
    {
        $response = $this->controller()->redirectToCustomerLogin();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringEndsWith('/customer/login', $response->getTargetUrl());
    }

    public function testAnAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $this->assertRedirectsTo('/customer/login', static fn (FrontControllerUnderTest $controller) => $controller->checkAuth());
    }

    public function testAnEmptyCartIsSentBackToTheCartPage(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);

        $this->assertRedirectsTo(
            '/checkout/cart',
            static fn (FrontControllerUnderTest $controller) => $controller->guardCartNotEmpty($eventDispatcher),
        );
    }

    public function testAnOrderWithoutADeliveryChoiceIsSentBackToTheDeliveryStep(): void
    {
        $this->assertRedirectsTo('/checkout/delivery', static fn (FrontControllerUnderTest $controller) => $controller->guardValidDelivery());
    }

    public function testAnOrderWithoutAPaymentChoiceIsSentBackToThePaymentStep(): void
    {
        $this->assertRedirectsTo('/checkout/payment', static fn (FrontControllerUnderTest $controller) => $controller->guardValidInvoice());
    }

    private function assertRedirectsTo(string $expectedPath, callable $guard): void
    {
        try {
            $guard($this->controller());
        } catch (RedirectException $redirect) {
            self::assertStringEndsWith($expectedPath, $redirect->getUrl());

            return;
        }

        self::fail(\sprintf('Expected a RedirectException towards "%s".', $expectedPath));
    }

    private function controller(): FrontControllerUnderTest
    {
        $container = static::getContainer();

        $controller = new FrontControllerUnderTest();
        $controller->container = $container;
        $controller->securityContext = $container->get(SecurityContext::class);
        $controller->requestStack = $container->get('request_stack');

        return $controller;
    }
}

/**
 * Stands in for the front controller of a module: it inherits the wiring under test
 * and only widens the visibility of what it exercises.
 */
final class FrontControllerUnderTest extends BaseFrontController
{
    public function redirectToCustomerLogin(): RedirectResponse
    {
        return $this->generateRedirectFromRoute('customer_login');
    }

    public function guardCartNotEmpty(EventDispatcherInterface $eventDispatcher): void
    {
        $this->checkCartNotEmpty($eventDispatcher);
    }

    public function guardValidDelivery(): void
    {
        $this->checkValidDelivery();
    }

    public function guardValidInvoice(): void
    {
        $this->checkValidInvoice();
    }
}
