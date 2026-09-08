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

namespace Thelia\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Domain\Customer\Service\AuthenticationReturnUrl;

final class AuthenticationReturnUrlTest extends TestCase
{
    private const DEFAULT = '/account';

    public function testTheLinkOfAPageComesBackToThatPage(): void
    {
        [$service] = $this->serviceFor('/checkout/delivery?slot=12');

        self::assertSame('/checkout/delivery?slot=12', $service->of(Request::create('/checkout/delivery?slot=12')));
    }

    public function testConsumesTheParameterOfTheCurrentRequest(): void
    {
        [$service] = $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => '/checkout/cart']);

        self::assertSame('/checkout/cart', $service->consume(self::DEFAULT));
    }

    public function testFallsBackToTheDefaultWithoutAnything(): void
    {
        [$service] = $this->serviceFor('/customer/login');

        self::assertSame(self::DEFAULT, $service->consume(self::DEFAULT));
    }

    public function testRefusesAParameterLeavingTheShop(): void
    {
        [$service] = $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => 'https://evil.example.net/pwn']);

        self::assertSame(self::DEFAULT, $service->consume(self::DEFAULT));
    }

    public function testCapturedUrlSurvivesTheNextRequests(): void
    {
        $session = $this->session();

        $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => '/category/wine'], $session)[0]->capture();

        // The registration form, then its second step: neither carries the parameter.
        $this->serviceFor('/customer/register', session: $session)[0]->capture();
        $onSecondStep = $this->serviceFor('/customer/informations', session: $session)[0];

        self::assertSame('/category/wine', $onSecondStep->consume(self::DEFAULT));
        self::assertSame(self::DEFAULT, $onSecondStep->consume(self::DEFAULT), 'consume() forgets what it hands back');
    }

    public function testTheParameterOfTheRequestWinsOverWhatWasCaptured(): void
    {
        $session = $this->session();
        $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => '/category/wine'], $session)[0]->capture();

        $posted = $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => '/checkout/cart'], $session)[0];

        self::assertSame('/checkout/cart', $posted->consume(self::DEFAULT));
    }

    public function testForgetDropsWhatWasCaptured(): void
    {
        $session = $this->session();
        $this->serviceFor('/customer/login', [AuthenticationReturnUrl::PARAMETER => '/category/wine'], $session)[0]->capture();

        $service = $this->serviceFor('/customer/login', session: $session)[0];
        $service->forget();

        self::assertSame(self::DEFAULT, $service->consume(self::DEFAULT));
    }

    public function testWorksWithoutASession(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/customer/login'));
        $service = new AuthenticationReturnUrl($stack);

        $service->capture();
        $service->forget();

        self::assertSame(self::DEFAULT, $service->consume(self::DEFAULT));
    }

    /**
     * @return array{AuthenticationReturnUrl, Request}
     */
    private function serviceFor(string $uri, array $parameters = [], ?Session $session = null): array
    {
        $query = [] === $parameters ? '' : '?'.http_build_query($parameters);
        $request = Request::create($uri.$query);
        $request->setSession($session ?? $this->session());

        $stack = new RequestStack();
        $stack->push($request);

        return [new AuthenticationReturnUrl($stack), $request];
    }

    private function session(): Session
    {
        return new Session(new MockArraySessionStorage());
    }
}
