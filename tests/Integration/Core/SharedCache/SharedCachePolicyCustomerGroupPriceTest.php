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
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\Currency;
use Thelia\Model\CustomerGroupQuery;
use Thelia\Model\Sale;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Trait\ResetsPriceMemos;

/**
 * A page shared between visitors is the same page for all of them: a request with no cookie and
 * no token is a visitor with no account, who belongs to the default customer group. A group that
 * pays a price of its own, or less than the catalog, changes nothing to that page, whoever it
 * pays: the visitor and the customers of the default group are the only ones it is served to.
 * A customer always carries a cookie or a token, and is never served from the shared cache in the
 * first place.
 */
final class SharedCachePolicyCustomerGroupPriceTest extends IntegrationTestCase
{
    use ResetsPriceMemos;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->resetPriceMemos();
    }

    protected function tearDown(): void
    {
        $this->resetPriceMemos();

        parent::tearDown();
    }

    public function testAPageIsSharedWhileNothingPricesPerGroup(): void
    {
        self::assertTrue($this->isShareable());
    }

    public function testAPageIsStillSharedOnceAGroupHasADiscount(): void
    {
        $this->factory->customerGroup(discount: 10.0);
        $this->resetPriceMemos();

        self::assertTrue($this->isShareable());
    }

    public function testAPageIsStillSharedOnceAGroupHasAPriceOfItsOwn(): void
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), Currency::getDefaultCurrency(), ['basePrice' => 100.0]);
        $this->factory->customerGroupProductPrice($this->factory->customerGroup(), $product->getDefaultSaleElements(), Currency::getDefaultCurrency(), 42.0);
        $this->resetPriceMemos();

        self::assertTrue($this->isShareable());
    }

    public function testAPageIsStillSharedWhileTheDefaultGroupItselfIsDiscounted(): void
    {
        CustomerGroupQuery::findDefault()->setDiscount('12.000000')->save($this->getPropelConnection());
        $this->resetPriceMemos();

        self::assertTrue($this->isShareable(), 'every visitor is in the default group: they are all owed the same price');
    }

    public function testAPageIsNotSharedWhileARuleIsReservedForNamedCustomersOrGroups(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $this->resetPriceMemos();

        self::assertFalse($this->isShareable());
    }

    public function testAPageIsNotSharedWhileAnOperationIsReservedForNamedCustomersOrGroups(): void
    {
        $this->factory->sale(['active' => true, 'audienceMode' => Sale::AUDIENCE_MODE_CUSTOMER_GROUPS]);
        $this->resetPriceMemos();

        self::assertFalse($this->isShareable());
    }

    public function testACustomerIsNeverServedFromTheSharedCache(): void
    {
        self::assertFalse($this->isShareable(['HTTP_AUTHORIZATION' => 'Bearer token']));
        self::assertFalse($this->isShareable([], ['PHPSESSID' => 'abc']));
    }

    /**
     * @param array<string, string> $server
     * @param array<string, string> $cookies
     */
    private function isShareable(array $server = [], array $cookies = []): bool
    {
        $request = TheliaRequest::create('/horatio.html', 'GET', [], $cookies, [], $server);
        $request->attributes->set('_view', 'product');

        $policy = new SharedCachePolicy(
            $this->getService(CatalogActivityProbe::class),
            new SharedCacheKeyCollector(SharedCacheKeyCollector::READABLE_PREFIXES),
        );

        return $policy->isShareable($request, new Response('<html></html>'));
    }
}
