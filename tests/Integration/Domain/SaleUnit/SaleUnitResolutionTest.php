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

namespace Thelia\Tests\Integration\Domain\SaleUnit;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\SaleUnit\Exception\NoCurrentSaleUnitException;
use Thelia\Domain\SaleUnit\Resolver\ApiHeaderSaleUnitResolver;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Domain\SaleUnit\Service\SaleUnitService;
use Thelia\Model\SaleUnit;
use Thelia\Test\IntegrationTestCase;

/**
 * How a front request finds the sale unit it sells from: the domain first,
 * then the session, then the API header — and above all, how a shop with no
 * active sale unit stays exactly the single shop it was.
 */
final class SaleUnitResolutionTest extends IntegrationTestCase
{
    private SaleUnitService $saleUnitService;

    private SaleUnitContext $saleUnitContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->saleUnitService = $this->getService(SaleUnitService::class);
        $this->saleUnitContext = $this->getService(SaleUnitContext::class);
    }

    protected function tearDown(): void
    {
        // The context and the memoised checkers are request-scoped: a test that set
        // them directly, without a request lifecycle to reset them, would leak into
        // the next one. Reset here what the request would have reset on its own.
        $this->getService(SaleUnitContext::class)->reset();
        $this->getService(SaleUnitActivityChecker::class)->reset();

        parent::tearDown();
    }

    public function testAShopWithNoSaleUnitKeepsANullContext(): void
    {
        $this->saleUnitService->handleRequest($this->frontRequest('https://shop.example.com/'));

        self::assertNull($this->saleUnitContext->getSaleUnit());
    }

    public function testAnInactiveSaleUnitDoesNotTurnTheMultiSaleUnitModeOn(): void
    {
        $this->createFixtureFactory()->saleUnit(['active' => false, 'url' => 'shop-a.example.com']);

        $this->saleUnitService->handleRequest($this->frontRequest('https://shop-a.example.com/'));

        self::assertNull($this->saleUnitContext->getSaleUnit());
    }

    public function testTheDomainResolvesTheSaleUnitOfTheRequestHost(): void
    {
        $fixtures = $this->createFixtureFactory();
        $fixtures->saleUnit(['url' => 'shop-a.example.com']);
        $shopB = $fixtures->saleUnit(['url' => 'shop-b.example.com']);

        $request = $this->frontRequest('https://shop-b.example.com/some-category.html');
        $this->saleUnitService->handleRequest($request);

        self::assertSame($shopB->getId(), $this->saleUnitContext->getSaleUnit()?->getId());
        self::assertSame($shopB->getId(), $this->sessionOf($request)->getSaleUnitId());
    }

    public function testTheDomainOutranksTheSessionOfAnotherSaleUnit(): void
    {
        $fixtures = $this->createFixtureFactory();
        $shopA = $fixtures->saleUnit(['url' => 'shop-a.example.com']);
        $shopB = $fixtures->saleUnit(['url' => 'shop-b.example.com']);

        $request = $this->frontRequest('https://shop-b.example.com/');
        $this->sessionOf($request)->setSaleUnitId($shopA->getId());

        $this->saleUnitService->handleRequest($request);

        self::assertSame($shopB->getId(), $this->saleUnitContext->getSaleUnit()?->getId());
        self::assertSame(
            $shopB->getId(),
            $this->sessionOf($request)->getSaleUnitId(),
            'a visitor who moved to another shop domain must not be moved back by their session on the next request',
        );
    }

    public function testTheSessionResolvesWhenNoDomainMatches(): void
    {
        $shopA = $this->createFixtureFactory()->saleUnit(['url' => null]);

        $request = $this->frontRequest('https://national.example.com/');
        $this->sessionOf($request)->setSaleUnitId($shopA->getId());

        $this->saleUnitService->handleRequest($request);

        self::assertSame($shopA->getId(), $this->saleUnitContext->getSaleUnit()?->getId());
    }

    public function testADeactivatedSaleUnitStopsBeingServedFromTheSession(): void
    {
        $fixtures = $this->createFixtureFactory();
        $turnedOff = $fixtures->saleUnit(['active' => false, 'url' => null]);
        // Another unit keeps the multi-sale-unit mode on: with none active at all,
        // a null context would be the single-shop mode, not the guard under test.
        $fixtures->saleUnit(['url' => 'shop-b.example.com']);

        $request = $this->frontRequest('https://national.example.com/');
        $this->sessionOf($request)->setSaleUnitId($turnedOff->getId());

        $this->saleUnitService->handleRequest($request);

        self::assertNull($this->saleUnitContext->getSaleUnit());
    }

    public function testTheApiHeaderResolvesBySaleUnitCode(): void
    {
        $shopB = $this->createFixtureFactory()->saleUnit(['code' => 'shop-b', 'url' => null]);

        $request = $this->frontRequest('https://api.example.com/api/front/products');
        $request->headers->set(ApiHeaderSaleUnitResolver::HEADER, 'shop-b');

        $this->saleUnitService->handleRequest($request);

        self::assertSame($shopB->getId(), $this->saleUnitContext->getSaleUnit()?->getId());
    }

    public function testAnUnresolvedRequestKeepsANullContext(): void
    {
        $this->createFixtureFactory()->saleUnit(['url' => 'shop-a.example.com']);

        $this->saleUnitService->handleRequest($this->frontRequest('https://unknown.example.com/'));

        self::assertNull($this->saleUnitContext->getSaleUnit());
    }

    public function testRequireSaleUnitThrowsWhenNothingResolved(): void
    {
        $this->expectException(NoCurrentSaleUnitException::class);

        $this->saleUnitContext->requireSaleUnit();
    }

    public function testSetCurrentSaleUnitWithoutARequestOnlyFillsTheContext(): void
    {
        $shopA = $this->createFixtureFactory()->saleUnit();

        $this->saleUnitService->setCurrentSaleUnit($shopA);

        self::assertInstanceOf(SaleUnit::class, $this->saleUnitContext->getSaleUnit());
        self::assertSame($shopA->getId(), $this->saleUnitContext->getSaleUnit()->getId());
    }

    private function frontRequest(string $url): Request
    {
        $request = Request::create($url);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function sessionOf(Request $request): Session
    {
        $session = $request->getSession();
        \assert($session instanceof Session);

        return $session;
    }
}
