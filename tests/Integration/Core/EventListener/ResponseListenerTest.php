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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Thelia\Core\EventListener\ResponseListener;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Test\IntegrationTestCase;

final class ResponseListenerTest extends IntegrationTestCase
{
    private ResponseListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new ResponseListener();
    }

    public function testBeforeResponseSetsCartCookieWhenSessionHasCartId(): void
    {
        $request = TheliaRequest::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('cart_use_cookie', 'cart-token-abc');
        $request->setSession($session);

        $response = new Response();
        $event = new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->beforeResponse($event);

        $cookies = $response->headers->getCookies();
        $cartCookie = null;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'cart')) {
                $cartCookie = $cookie;
                break;
            }
        }

        self::assertNotNull($cartCookie, 'A cart cookie must be set');
        self::assertSame('cart-token-abc', $cartCookie->getValue());
        self::assertNull($session->get('cart_use_cookie'), 'Session flag must be cleared');
    }

    public function testBeforeResponseClearsCookieWhenCartIdIsEmpty(): void
    {
        $request = TheliaRequest::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('cart_use_cookie', '');
        $request->setSession($session);

        $response = new Response();
        $event = new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->beforeResponse($event);

        $cookies = $response->headers->getCookies();
        $cartCookie = null;
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'cart')) {
                $cartCookie = $cookie;
                break;
            }
        }

        self::assertNotNull($cartCookie, 'A clearing cookie must be set');
        self::assertTrue($cartCookie->isCleared());
    }

    public function testBeforeResponseDoesNothingWithoutSessionFlag(): void
    {
        $request = TheliaRequest::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);

        $response = new Response();
        $event = new ResponseEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->listener->beforeResponse($event);

        self::assertEmpty($response->headers->getCookies());
    }
}
