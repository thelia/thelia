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

namespace Thelia\Tests\Unit\Domain\Pricing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Pricing\CatalogActivity;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Domain\Pricing\EffectivePrice;
use Thelia\Domain\Pricing\EffectivePriceCatalog;
use Thelia\Domain\Pricing\EffectivePriceResolver;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Tests\Support\Pricing\LoadedCustomerDiscountRate;

final class EffectivePriceCatalogTest extends TestCase
{
    /** @var list<array{list<int>, int, int}> the sale elements, customer id and currency id of each resolution */
    private array $resolutions = [];

    public function testTheResolverIsAskedForEachSaleElementOnceAsIntegers(): void
    {
        $catalog = $this->catalogPricing([]);

        $catalog->warm(['9', 7, 9, 3], $this->currency(1), $this->customer(42));

        self::assertSame([[[9, 7, 3], 42, 1]], $this->resolutions);
    }

    public function testNothingIsResolvedWhileNothingPricesForTheVisitor(): void
    {
        $catalog = $this->catalogPricing([5 => 50.0], publicRule: false, namedRule: false, reservedSale: false);

        self::assertSame([], $catalog->warm([5], $this->currency(1), $this->customer(42)));
        self::assertSame([], $catalog->warm([5], $this->currency(1), null));
        self::assertSame([], $this->resolutions);
    }

    public function testASaleElementAlreadyAnsweredIsNotResolvedAgainAMissIncluded(): void
    {
        $catalog = $this->catalogPricing([1 => 10.0, 2 => 20.0]);
        $currency = $this->currency(1);

        $catalog->warm([1, 3], $currency, null);
        $prices = $catalog->warm([3, 1, 2], $currency, null);

        self::assertSame([[[1, 3], 0, 1], [[2], 0, 1]], $this->resolutions);
        self::assertSame([1, 2], array_keys($prices));
        self::assertSame(20.0, $catalog->warm([2], $currency, null)[2]->untaxedPromoPrice);
        self::assertCount(2, $this->resolutions);
    }

    public function testAGuestAndTheCustomerNumberOneDoNotShareTheirPrices(): void
    {
        $catalog = $this->catalogPricing([5 => 50.0]);
        $currency = $this->currency(1);

        $catalog->warm([5], $currency, null);
        $catalog->warm([5], $currency, $this->customer(1));

        self::assertSame([[[5], 0, 1], [[5], 1, 1]], $this->resolutions);
    }

    public function testTwoCurrenciesDoNotShareTheirPrices(): void
    {
        $catalog = $this->catalogPricing([5 => 50.0]);

        $catalog->warm([5], $this->currency(1), null);
        $catalog->warm([5], $this->currency(2), null);

        self::assertSame([[[5], 0, 1], [[5], 0, 2]], $this->resolutions);
    }

    public function testAResetForgetsWhatWasResolved(): void
    {
        $catalog = $this->catalogPricing([5 => 50.0]);

        $catalog->warm([5], $this->currency(1), null);
        $catalog->reset();
        $catalog->warm([5], $this->currency(1), null);

        self::assertCount(2, $this->resolutions);
    }

    /**
     * @return iterable<string, array{bool, bool, bool, bool, bool}>
     */
    public static function visitors(): iterable
    {
        yield 'a public rule prices for a guest' => [true, false, false, false, true];
        yield 'a guest is priced by no reserved rule nor reserved sale' => [false, false, true, true, false];
        yield 'a customer with nothing running' => [false, true, false, false, false];
        yield 'a customer while a reserved rule runs' => [false, true, true, false, true];
        yield 'a customer while a reserved sale runs' => [false, true, false, true, true];
        yield 'a customer while a reserved rule and a reserved sale run' => [false, true, true, true, true];
    }

    #[DataProvider('visitors')]
    public function testWhetherAnythingPricesForTheVisitor(bool $publicRule, bool $signedIn, bool $namedRule, bool $reservedSale, bool $expected): void
    {
        $checker = $this->activityChecker($publicRule, $namedRule, $reservedSale);

        self::assertSame($expected, $checker->mayResolveEffectivePriceFor($signedIn ? $this->customer(42) : null));
    }

    /**
     * @param array<int, float> $untaxedPricesBySaleElement
     */
    private function catalogPricing(
        array $untaxedPricesBySaleElement,
        bool $publicRule = true,
        bool $namedRule = false,
        bool $reservedSale = false,
    ): EffectivePriceCatalog {
        $resolver = $this->createStub(EffectivePriceResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            function (array $productSaleElementsIds, Currency $currency, ?Customer $customer) use ($untaxedPricesBySaleElement): array {
                $this->resolutions[] = [$productSaleElementsIds, $customer?->getId() ?? 0, $currency->getId()];
                $prices = [];

                foreach ($productSaleElementsIds as $pseId) {
                    if (isset($untaxedPricesBySaleElement[$pseId])) {
                        $prices[$pseId] = new EffectivePrice($pseId, $untaxedPricesBySaleElement[$pseId], false, EffectivePrice::SOURCE_RULE, 1);
                    }
                }

                return $prices;
            },
        );

        return new EffectivePriceCatalog($resolver, $this->activityChecker($publicRule, $namedRule, $reservedSale));
    }

    private function activityChecker(bool $publicRule, bool $namedRule, bool $reservedSale): PricingActivityChecker
    {
        $probe = $this->createStub(CatalogActivityProbe::class);
        $probe->method('read')->willReturn(new CatalogActivity($publicRule, $namedRule, $reservedSale, false, false, false, 1));

        return new PricingActivityChecker($probe, new LoadedCustomerDiscountRate());
    }

    private function currency(int $id): Currency
    {
        $currency = new Currency();
        $currency->setId($id);

        return $currency;
    }

    private function customer(int $id): Customer
    {
        $customer = new Customer();
        $customer->setId($id);

        return $customer;
    }
}
