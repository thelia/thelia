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

namespace Thelia\Tests\Unit\Domain\Pricing\Rule;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\ResolvedCatalogPrice;
use Thelia\Domain\Pricing\Rule\Audience\CustomerAudience;
use Thelia\Domain\Pricing\Rule\CatalogPriceRuleResolver;
use Thelia\Domain\Pricing\Rule\Engine\PriceChainEvaluator;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Domain\Pricing\Rule\Storage\PublicPriceReader;
use Thelia\Domain\Pricing\Rule\Storage\RuleEffectFactory;
use Thelia\Domain\Pricing\Rule\Storage\ScopeReader;
use Thelia\Domain\Pricing\Rule\Storage\TaxCalculatorLoader;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * The visitors no reserved rule prices for: the stored public prices are their
 * whole answer.
 */
final class CatalogPriceRuleResolverTest extends TestCase
{
    private Currency $currency;

    /** @var array<int, ResolvedCatalogPrice> */
    private array $storedPrices;

    protected function setUp(): void
    {
        $this->currency = new Currency();
        $this->currency->setId(1);
        $this->storedPrices = [
            9 => new ResolvedCatalogPrice(9, 72.0, 4, true),
            3 => new ResolvedCatalogPrice(3, 24.0, 4, true),
        ];
    }

    public function testTheStoredPricesAreReadForEachSaleElementOnceAsIntegersAtTheGivenInstant(): void
    {
        $now = new \DateTimeImmutable('-1 hour');
        $reader = $this->createMock(PublicPriceReader::class);
        $reader->expects(self::once())
            ->method('currentPrices')
            ->with(self::identicalTo([9, 7, 3]), $this->currency, self::identicalTo($now))
            ->willReturn($this->storedPrices);

        $resolver = $this->resolver($reader, $this->activity(namedRule: false), $this->createStub(CustomerAudience::class));

        self::assertSame($this->storedPrices, $resolver->resolve(['9', 7, 9, 3], $this->currency, null, $now));
    }

    public function testACustomerIsNotLookedUpWhileNoReservedRuleRuns(): void
    {
        $audience = $this->createMock(CustomerAudience::class);
        $audience->expects(self::never())->method('entitledRuleIds');

        $resolver = $this->resolver($this->storedReader(), $this->activity(namedRule: false), $audience);

        self::assertSame($this->storedPrices, $resolver->resolve([9, 3], $this->currency, $this->customer()));
    }

    private function resolver(PublicPriceReader $reader, PricingActivityChecker $activity, CustomerAudience $audience): CatalogPriceRuleResolver
    {
        return new CatalogPriceRuleResolver(
            $reader,
            $activity,
            $audience,
            $this->createStub(ScopeReader::class),
            $this->createStub(BasePriceLoader::class),
            $this->createStub(TaxCalculatorLoader::class),
            $this->createStub(RuleEffectFactory::class),
            new PriceChainEvaluator(),
        );
    }

    private function storedReader(): PublicPriceReader
    {
        $reader = $this->createStub(PublicPriceReader::class);
        $reader->method('currentPrices')->willReturn($this->storedPrices);

        return $reader;
    }

    private function activity(bool $namedRule): PricingActivityChecker
    {
        $activity = $this->createStub(PricingActivityChecker::class);
        $activity->method('hasActivePublicRule')->willReturn(true);
        $activity->method('hasActiveNamedRule')->willReturn($namedRule);

        return $activity;
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->setId(42);

        return $customer;
    }
}
