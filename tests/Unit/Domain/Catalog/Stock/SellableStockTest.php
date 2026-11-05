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

namespace Thelia\Tests\Unit\Domain\Catalog\Stock;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Stock\NoReservedQuantity;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\ReservedQuantityProvider;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Model\ProductSaleElements;

final class SellableStockTest extends TestCase
{
    public function testTheModeThatRefusesSellsTheStockAndNothingBelowZero(): void
    {
        self::assertSame(3.0, SellableStock::ceilingOf(3.0, OutOfStockPolicy::Refuse, 10.0));
        self::assertSame(0.0, SellableStock::ceilingOf(0.0, OutOfStockPolicy::Refuse, null));
    }

    public function testTheLimitedModeSellsTheStockPlusTheLimit(): void
    {
        self::assertSame(10.0, SellableStock::ceilingOf(0.0, OutOfStockPolicy::Limited, 10.0), 'Stock 0, limit 10: ten can be sold.');
        self::assertSame(13.0, SellableStock::ceilingOf(3.0, OutOfStockPolicy::Limited, 10.0), 'Stock 3, limit 10: thirteen can be sold.');
        self::assertSame(0.0, SellableStock::ceilingOf(-10.0, OutOfStockPolicy::Limited, 10.0), 'The limit is already used up.');
        self::assertSame(3.0, SellableStock::ceilingOf(3.0, OutOfStockPolicy::Limited, null), 'A limit that is not set allows nothing below zero.');
    }

    public function testTheUnlimitedModeHasNoCeiling(): void
    {
        self::assertNull(SellableStock::ceilingOf(0.0, OutOfStockPolicy::Unlimited, null));
        self::assertNull(SellableStock::ceilingOf(-4.0, OutOfStockPolicy::Unlimited, 10.0));
    }

    public function testTheReservationsOfOtherCartsAreTakenFromWhatMayBeSold(): void
    {
        $stock = new SellableStock($this->reserving(1.0));
        $saleElements = $this->saleElements(1.0, OutOfStockPolicy::Refuse);

        self::assertSame(0.0, $stock->forPse($saleElements, null, null, 99), 'Physical 1, another cart holds 1: nothing for this one.');
    }

    public function testTheReservationsAreAskedForTheCartAndTheTargetOfTheQuestion(): void
    {
        $provider = new class implements ReservedQuantityProvider {
            /** @var list<array{0: int, 1: ?int, 2: ?int}> */
            public array $asked = [];

            public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
            {
                $this->asked[] = [$productSaleElementsId, $saleUnitId, $excludedCartId];

                return 0.0;
            }

            public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId): array
            {
                return [];
            }
        };

        (new SellableStock($provider))->forPse($this->saleElements(5.0, OutOfStockPolicy::Refuse, 42), null, 7, 99);

        self::assertSame([[42, 7, 99]], $provider->asked);
    }

    public function testTheReservationsEatTheLimitOfAPreorderToo(): void
    {
        $stock = new SellableStock($this->reserving(4.0));

        self::assertSame(6.0, $stock->forPse($this->saleElements(0.0, OutOfStockPolicy::Limited, 1, 10.0)));
    }

    public function testNothingIsReservedAgainstAnUnlimitedMode(): void
    {
        $stock = new SellableStock($this->reserving(100.0));

        self::assertNull($stock->forPse($this->saleElements(0.0, OutOfStockPolicy::Unlimited)));
    }

    public function testWhatMayBeSoldIsNeverBelowZero(): void
    {
        $stock = new SellableStock($this->reserving(50.0));

        self::assertSame(0.0, $stock->forPse($this->saleElements(3.0, OutOfStockPolicy::Refuse)));
        self::assertSame(0.0, SellableStock::withoutReservations()->forPse($this->saleElements(-2.0, OutOfStockPolicy::Refuse)));
    }

    public function testTheStockOfASaleUnitStandsInForTheStockOfTheSaleElement(): void
    {
        $stock = SellableStock::withoutReservations();

        self::assertSame(2.0, $stock->forPse($this->saleElements(50.0, OutOfStockPolicy::Refuse), 2.0));
        self::assertSame(12.0, $stock->forPse($this->saleElements(50.0, OutOfStockPolicy::Limited, 1, 10.0), 2.0));
    }

    public function testTheDefaultProviderReservesNothing(): void
    {
        self::assertSame(0.0, (new NoReservedQuantity())->reservedByOtherCarts(1, 2, 3));
    }

    public function testABatchAsksTheReservationsOnceForEverySaleElement(): void
    {
        $provider = new class implements ReservedQuantityProvider {
            /** @var list<array{0: list<int>, 1: ?int, 2: ?int}> */
            public array $asked = [];

            public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
            {
                throw new \LogicException('A batch reads the reservations by the batch.');
            }

            public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId): array
            {
                $this->asked[] = [$productSaleElementsIds, $saleUnitId, $excludedCartId];

                return [1 => 2.0, 3 => 10.0];
            }
        };

        $answers = (new SellableStock($provider))->forMany([
            $this->saleElements(5.0, OutOfStockPolicy::Refuse, 1),
            $this->saleElements(0.0, OutOfStockPolicy::Unlimited, 2),
            $this->saleElements(0.0, OutOfStockPolicy::Limited, 3, 10.0),
            $this->saleElements(4.0, OutOfStockPolicy::Refuse, 4),
        ], 7, 99);

        self::assertSame([[[1, 2, 3, 4], 7, 99]], $provider->asked, 'One read: even the one that sells without limit, whose preorder depends on what is held.');
        self::assertSame([1 => 3.0, 2 => null, 3 => 0.0, 4 => 4.0], $answers);
    }

    public function testABatchOfNothingAsksNothing(): void
    {
        self::assertSame([], (new SellableStock($this->reserving(1.0)))->forMany([]));
    }

    public function testTheAnswerTellsAPreorderByTheSingleRuleOfThePreorder(): void
    {
        $stock = new SellableStock($this->reserving(3.0));
        $answer = $stock->answersFor([$this->saleElements(3.0, OutOfStockPolicy::Unlimited, 1)])[1];

        self::assertSame(0.0, $answer->physicalQuantity - $answer->reservedByOtherCarts);
        self::assertTrue($answer->nextUnitIsPreorder(), 'Physical 3, other carts hold 3: the next unit is on preorder.');
        self::assertTrue($answer->isPreorderFor(2.0));

        $refusing = $stock->answersFor([$this->saleElements(3.0, OutOfStockPolicy::Refuse, 1)])[1];
        self::assertFalse($refusing->nextUnitIsPreorder(), 'A mode that refuses sells no preorder.');

        $usedUp = $stock->answersFor([$this->saleElements(-7.0, OutOfStockPolicy::Limited, 1, 10.0)])[1];
        self::assertFalse($usedUp->nextUnitIsPreorder(), 'A limit used up sells nothing: not a preorder to offer.');
    }

    private function reserving(float $reserved): ReservedQuantityProvider
    {
        return new class($reserved) implements ReservedQuantityProvider {
            public function __construct(private float $reserved)
            {
            }

            public function reservedByOtherCarts(int $productSaleElementsId, ?int $saleUnitId, ?int $excludedCartId): float
            {
                return $this->reserved;
            }

            public function reservedByOtherCartsOf(array $productSaleElementsIds, ?int $saleUnitId, ?int $excludedCartId): array
            {
                return array_fill_keys($productSaleElementsIds, $this->reserved);
            }
        };
    }

    private function saleElements(float $quantity, OutOfStockPolicy $policy, int $id = 1, ?float $limit = null): ProductSaleElements
    {
        $saleElements = new ProductSaleElements();
        $saleElements->setId($id);
        $saleElements->setQuantity($quantity);
        $saleElements->setOutOfStockPolicy($policy->value);
        $saleElements->setPreorderLimit($limit);

        return $saleElements;
    }
}
