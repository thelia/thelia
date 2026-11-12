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

namespace Thelia\Tests\Integration\Core\SharedCache;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\SharedCache\SharedCacheKeyCollector;
use Thelia\Core\SharedCache\SharedCachePolicy;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\PriceVisibility\PriceDisplayMode;
use Thelia\Model\CatalogRestriction;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Integration\Domain\Pricing\PriceList\PriceListFixtures;
use Thelia\Tests\Integration\Domain\Pricing\PriceVisibility\PriceDisplayFixtures;

/**
 * A page shared between visitors is the page of the visitors of the default group. It is no longer
 * shared once what a visitor is shown is not the plain catalog: the price display mode of the shop
 * or of a product is not visible, or a price list or a catalog restriction is assigned to the
 * default group. What is assigned to another group, to a customer, or is turned off changes
 * nothing to it.
 */
final class SharedCachePolicyPriceDisplayTest extends IntegrationTestCase
{
    use PriceDisplayFixtures;
    use PriceListFixtures;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->forgetPriceDisplayMemos();
    }

    protected function tearDown(): void
    {
        $this->forgetPriceDisplayMemos();

        parent::tearDown();
    }

    public function testAPageIsSharedWhileEveryPriceIsVisible(): void
    {
        self::assertTrue($this->isShareable());
    }

    public function testAPageIsNotSharedOnceTheShopShowsItsPricesToCustomersOnly(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);

        self::assertFalse($this->isShareable());
    }

    public function testAPageIsNotSharedOnceTheShopIsOnRequest(): void
    {
        $this->shopShows(PriceDisplayMode::OnRequest);

        self::assertFalse($this->isShareable());
    }

    public function testAPageIsNotSharedOnceOneProductHidesItsPrice(): void
    {
        $this->productShows($this->pricedProduct(), PriceDisplayMode::OnRequest);

        self::assertFalse($this->isShareable());
    }

    public function testAPageIsSharedAgainOnceTheModeIsBackToVisible(): void
    {
        $this->shopShows(PriceDisplayMode::LoggedIn);
        self::assertFalse($this->isShareable());

        $this->shopShows(PriceDisplayMode::Visible);

        self::assertTrue($this->isShareable());
    }

    public function testAPageIsNotSharedOnceAPriceListIsAssignedToTheDefaultGroup(): void
    {
        $list = $this->priceList();
        $this->assignToGroup($list, CustomerGroupQuery::findDefault());
        $this->forgetPriceDisplayMemos();

        self::assertFalse($this->isShareable());
    }

    public function testAPriceListOfAnotherGroupOrOfACustomerOrOfNobodyChangesNothingToIt(): void
    {
        $other = $this->factory->customerGroup();
        $assigned = $this->priceList();
        $this->assignToGroup($assigned, $other);
        $this->assignToCustomer($assigned, $this->memberOf($other));
        $this->priceList();
        $off = $this->priceList(active: false);
        $this->assignToGroup($off, CustomerGroupQuery::findDefault());
        $this->forgetPriceDisplayMemos();

        self::assertTrue($this->isShareable());
    }

    public function testAPageIsNotSharedOnceARestrictionIsAssignedToTheDefaultGroup(): void
    {
        (new CatalogRestriction())
            ->setCustomerGroupId(CustomerGroupQuery::findDefault()->getId())
            ->setProductId($this->pricedProduct()->getId())
            ->setMode('deny')
            ->save($this->getPropelConnection());
        $this->forgetPriceDisplayMemos();

        self::assertFalse($this->isShareable());
    }

    public function testARestrictionOfAnotherGroupChangesNothingToIt(): void
    {
        (new CatalogRestriction())
            ->setCustomerGroupId($this->factory->customerGroup()->getId())
            ->setProductId($this->pricedProduct()->getId())
            ->setMode('deny')
            ->save($this->getPropelConnection());
        $this->forgetPriceDisplayMemos();

        self::assertTrue($this->isShareable());
    }

    private function isShareable(): bool
    {
        $request = TheliaRequest::create('/horatio.html', 'GET');
        $request->attributes->set('_view', 'product');

        $policy = new SharedCachePolicy(
            $this->getService(CatalogActivityProbe::class),
            new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES),
        );

        return $policy->isShareable($request, new Response('<html></html>'));
    }
}
