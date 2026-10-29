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

namespace Thelia\Tests\Integration\Domain\Pricing;

use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * Whether any rule prices the catalog, asked of the database once per request.
 */
final class PricingActivityCheckerTest extends ActionIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Whatever rule the test database holds is turned off, so each test starts from a shop without any.
        CatalogPriceRuleQuery::create()->update(['Active' => false]);
    }

    public function testAPublicRuleTurnedOnLaterInTheRequestIsSeenAfterTheReset(): void
    {
        $checker = $this->checker(false);

        self::assertFalse($checker->hasActivePublicRule());

        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);

        self::assertFalse($checker->hasActivePublicRule(), 'the answer is kept for the request');
        self::assertFalse($checker->hasActiveNamedRule());

        $checker->reset();

        self::assertTrue($checker->hasActivePublicRule());
    }

    public function testAReservedRuleTurnedOnLaterInTheRequestIsSeenAfterTheReset(): void
    {
        $checker = $this->checker(false);

        self::assertFalse($checker->hasActiveNamedRule());

        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertFalse($checker->hasActiveNamedRule(), 'the answer is kept for the request');
        self::assertFalse($checker->hasActivePublicRule());

        $checker->reset();

        self::assertTrue($checker->hasActiveNamedRule());
    }

    public function testPricingDoesNotDependOnTheVisitorWhileNothingReservedRuns(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0]);
        $this->factory->catalogPriceRule(['active' => false, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertFalse($this->checker(false)->hasVisitorDependentPricing());
    }

    public function testATurnedOnReservedRuleMakesPricingDependOnTheVisitor(): void
    {
        $this->factory->catalogPriceRule(['active' => true, 'percentageValue' => 10.0, 'audienceMode' => CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS]);

        self::assertTrue($this->checker(false)->hasVisitorDependentPricing());
    }

    public function testARunningReservedSaleMakesPricingDependOnTheVisitor(): void
    {
        self::assertTrue($this->checker(true)->hasVisitorDependentPricing());
    }

    private function checker(bool $hasActiveReservedSale): PricingActivityChecker
    {
        $saleAudienceChecker = $this->createStub(SaleAudienceChecker::class);
        $saleAudienceChecker->method('hasActiveReservedSale')->willReturn($hasActiveReservedSale);

        return new PricingActivityChecker($saleAudienceChecker, new BundleCatalogState());
    }
}
