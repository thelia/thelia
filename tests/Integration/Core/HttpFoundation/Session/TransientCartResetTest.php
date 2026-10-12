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

namespace Thelia\Tests\Integration\Core\HttpFoundation\Session;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpFoundation\Session\TransientCartReset;
use Thelia\Model\Cart;
use Thelia\Test\IntegrationTestCase;

/**
 * A visitor who has not saved a cart yet gets one kept in memory. A process that
 * serves several requests in a row (a worker) must not hand it to the next visitor.
 */
final class TransientCartResetTest extends IntegrationTestCase
{
    public function testTheSessionsOfOneRequestShareTheCartNotSavedYet(): void
    {
        $cart = $this->cartNotSavedYetOf(new Session(new MockArraySessionStorage()));

        self::assertSame($cart, (new Session(new MockArraySessionStorage()))->getSessionCart($this->dispatcher()));
    }

    public function testTheNextRequestOfTheProcessDoesNotGetTheCartOfThePreviousVisitor(): void
    {
        $cart = $this->cartNotSavedYetOf(new Session(new MockArraySessionStorage()));

        $this->listener()->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));

        self::assertNotSame($cart, (new Session(new MockArraySessionStorage()))->getSessionCart($this->dispatcher()));
    }

    public function testASubRequestKeepsTheCartOfItsMainRequest(): void
    {
        $cart = $this->cartNotSavedYetOf(new Session(new MockArraySessionStorage()));

        $this->listener()->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));

        self::assertSame($cart, (new Session(new MockArraySessionStorage()))->getSessionCart($this->dispatcher()));
    }

    public function testResettingTheServicesBetweenTwoRequestsForgetsTheCart(): void
    {
        $cart = $this->cartNotSavedYetOf(new Session(new MockArraySessionStorage()));

        $this->listener()->reset();

        self::assertNotSame($cart, (new Session(new MockArraySessionStorage()))->getSessionCart($this->dispatcher()));
    }

    public function testTheResetListensToTheStartOfEveryRequest(): void
    {
        $listeners = array_filter(
            $this->dispatcher()->getListeners(KernelEvents::REQUEST),
            static fn (mixed $listener): bool => \is_array($listener) && $listener[0] instanceof TransientCartReset,
        );

        self::assertCount(1, $listeners);
        self::assertSame('onKernelRequest', array_values($listeners)[0][1]);
    }

    private function cartNotSavedYetOf(Session $session): Cart
    {
        $cart = $session->getSessionCart($this->dispatcher());

        self::assertTrue($cart->isNew(), 'a visitor without a cart gets one kept in memory, not saved');

        return $cart;
    }

    private function requestEvent(int $requestType): RequestEvent
    {
        return new RequestEvent(static::$kernel, Request::create('http://localhost/'), $requestType);
    }

    private function listener(): TransientCartReset
    {
        return $this->getService(TransientCartReset::class);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->getService('event_dispatcher');
    }
}
